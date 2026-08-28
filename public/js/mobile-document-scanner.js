/**
 * Integrated mobile document scanner for Libromart.
 * Camera → detect/crop/perspective → multi-page → enhance → PDF → attach.
 */
(function () {
    function isMobileScannerDevice() {
        var ua = navigator.userAgent || '';
        if (/Android/i.test(ua)) return true;
        if (/iPhone|iPad|iPod/i.test(ua)) return true;
        if (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) return true;
        return false;
    }

    try {
        document.documentElement.classList.add(
            isMobileScannerDevice() ? 'lm-is-mobile' : 'lm-is-desktop'
        );
    } catch (e) {}

    var root = document.getElementById('lm-mobile-scanner');
    if (!root) return;
    if (root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    var video = document.getElementById('lm-scan-video');
    var liveCanvas = document.getElementById('lm-scan-live-overlay');
    var cropCanvas = document.getElementById('lm-scan-crop-canvas');
    var enhanceCanvas = document.getElementById('lm-scan-enhance-canvas');
    var pagesList = document.getElementById('lm-scan-pages-list');
    var statusEl = document.getElementById('lm-scan-status');
    var galleryInput = document.getElementById('lm-scan-gallery');
    var brightnessInput = document.getElementById('lm-scan-brightness');
    var stream = null;
    var liveTimer = null;
    var current = null;
    var captureSource = null;
    var corners = [];
    var draggingCorner = -1;
    var pages = [];
    var enhanceIndex = 0;
    var liveQuad = null;
    var step = 'capture';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showStatus(message, kind) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.classList.toggle('hidden', !message);
        statusEl.dataset.kind = kind || 'info';
    }

    function setStep(name) {
        step = name;
        root.querySelectorAll('[data-scan-step]').forEach(function (el) {
            el.classList.toggle('hidden', el.getAttribute('data-scan-step') !== name);
        });
    }

    function stopCamera() {
        if (liveTimer) {
            clearInterval(liveTimer);
            liveTimer = null;
        }
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        if (video) {
            video.srcObject = null;
        }
    }

    function closeScanner() {
        stopCamera();
        captureSource = null;
        corners = [];
        pages = [];
        liveQuad = null;
        draggingCorner = -1;
        root.classList.add('hidden');
        document.body.classList.remove('lm-scanner-open');
        showStatus('');
    }

    function canvasFromImage(source, maxDim) {
        maxDim = maxDim || 1800;
        var w = source.videoWidth || source.naturalWidth || source.width;
        var h = source.videoHeight || source.naturalHeight || source.height;
        var scale = Math.min(1, maxDim / Math.max(w, h));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    function cloneCanvas(source) {
        var canvas = document.createElement('canvas');
        canvas.width = source.width;
        canvas.height = source.height;
        canvas.getContext('2d').drawImage(source, 0, 0);
        return canvas;
    }

    function rotateCanvas(source, degrees) {
        var rad = (degrees * Math.PI) / 180;
        var canvas = document.createElement('canvas');
        var swap = degrees % 180 !== 0;
        canvas.width = swap ? source.height : source.width;
        canvas.height = swap ? source.width : source.height;
        var ctx = canvas.getContext('2d');
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(rad);
        ctx.drawImage(source, -source.width / 2, -source.height / 2);
        return canvas;
    }

    function defaultCorners(width, height) {
        var ix = width * 0.08;
        var iy = height * 0.08;
        return [
            { x: ix, y: iy },
            { x: width - ix, y: iy },
            { x: width - ix, y: height - iy },
            { x: ix, y: height - iy }
        ];
    }

    function orderCorners(pts) {
        var copy = pts.map(function (p) {
            return { x: p.x, y: p.y };
        });
        copy.sort(function (a, b) {
            return a.y - b.y;
        });
        var top = copy.slice(0, 2).sort(function (a, b) {
            return a.x - b.x;
        });
        var bottom = copy.slice(2, 4).sort(function (a, b) {
            return a.x - b.x;
        });
        return [top[0], top[1], bottom[1], bottom[0]];
    }

    function detectDocumentCorners(source) {
        var maxW = 360;
        var scale = Math.min(1, maxW / source.width);
        var w = Math.max(1, Math.round(source.width * scale));
        var h = Math.max(1, Math.round(source.height * scale));
        var tmp = document.createElement('canvas');
        tmp.width = w;
        tmp.height = h;
        var ctx = tmp.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(source, 0, 0, w, h);
        var img = ctx.getImageData(0, 0, w, h);
        var n = w * h;
        var gray = new Uint8Array(n);
        var data = img.data;
        var i;
        for (i = 0; i < n; i++) {
            var j = i * 4;
            gray[i] = (data[j] * 0.299 + data[j + 1] * 0.587 + data[j + 2] * 0.114) | 0;
        }

        var blurred = boxBlur(gray, w, h, 1);
        var mag = sobelMagnitude(blurred, w, h);
        var threshold = percentile(mag, 0.88);
        var edges = new Uint8Array(n);
        for (i = 0; i < n; i++) {
            edges[i] = mag[i] >= threshold ? 1 : 0;
        }
        dilate(edges, w, h);

        var visited = new Uint8Array(n);
        var best = null;
        var bestScore = 0;
        for (i = 0; i < n; i++) {
            if (!edges[i] || visited[i]) continue;
            var component = floodComponent(edges, visited, w, h, i);
            if (component.length < n * 0.012) continue;
            var quad = quadFromPoints(component, w, h);
            if (!quad) continue;
            var area = polygonArea(quad);
            var score = area;
            if (score > bestScore) {
                bestScore = score;
                best = quad;
            }
        }

        var minArea = w * h * 0.12;
        if (!best || bestScore < minArea) {
            return null;
        }

        var inv = 1 / scale;
        return orderCorners(best).map(function (p) {
            return {
                x: clamp(p.x * inv, 0, source.width - 1),
                y: clamp(p.y * inv, 0, source.height - 1)
            };
        });
    }

    function boxBlur(src, w, h, radius) {
        var out = new Uint8Array(src.length);
        var tmp = new Float32Array(src.length);
        var i;
        var x;
        var y;
        var sum;
        var count;
        var k;
        for (y = 0; y < h; y++) {
            sum = 0;
            count = 0;
            for (x = -radius; x < w; x++) {
                if (x + radius < w) {
                    sum += src[y * w + (x + radius)];
                    count += 1;
                }
                if (x - radius - 1 >= 0) {
                    sum -= src[y * w + (x - radius - 1)];
                    count -= 1;
                }
                if (x >= 0) {
                    tmp[y * w + x] = sum / count;
                }
            }
        }
        for (x = 0; x < w; x++) {
            sum = 0;
            count = 0;
            for (y = -radius; y < h; y++) {
                if (y + radius < h) {
                    sum += tmp[(y + radius) * w + x];
                    count += 1;
                }
                if (y - radius - 1 >= 0) {
                    sum -= tmp[(y - radius - 1) * w + x];
                    count -= 1;
                }
                if (y >= 0) {
                    out[y * w + x] = clamp(Math.round(sum / count), 0, 255);
                }
            }
        }
        return out;
    }

    function sobelMagnitude(gray, w, h) {
        var mag = new Float32Array(w * h);
        var x;
        var y;
        for (y = 1; y < h - 1; y++) {
            for (x = 1; x < w - 1; x++) {
                var i = y * w + x;
                var gx =
                    -gray[i - w - 1] +
                    gray[i - w + 1] -
                    2 * gray[i - 1] +
                    2 * gray[i + 1] -
                    gray[i + w - 1] +
                    gray[i + w + 1];
                var gy =
                    -gray[i - w - 1] -
                    2 * gray[i - w] -
                    gray[i - w + 1] +
                    gray[i + w - 1] +
                    2 * gray[i + w] +
                    gray[i + w + 1];
                mag[i] = Math.sqrt(gx * gx + gy * gy);
            }
        }
        return mag;
    }

    function percentile(values, p) {
        var copy = Array.prototype.slice.call(values).filter(function (v) {
            return v > 0;
        });
        if (!copy.length) return 0;
        copy.sort(function (a, b) {
            return a - b;
        });
        return copy[Math.min(copy.length - 1, Math.floor(copy.length * p))];
    }

    function dilate(bin, w, h) {
        var copy = new Uint8Array(bin);
        var x;
        var y;
        var dx;
        var dy;
        for (y = 1; y < h - 1; y++) {
            for (x = 1; x < w - 1; x++) {
                if (!copy[y * w + x]) continue;
                for (dy = -1; dy <= 1; dy++) {
                    for (dx = -1; dx <= 1; dx++) {
                        bin[(y + dy) * w + (x + dx)] = 1;
                    }
                }
            }
        }
    }

    function floodComponent(edges, visited, w, h, start) {
        var stack = [start];
        var points = [];
        visited[start] = 1;
        while (stack.length) {
            var i = stack.pop();
            points.push({ x: i % w, y: (i / w) | 0 });
            var neighbors = [i - 1, i + 1, i - w, i + w];
            for (var n = 0; n < neighbors.length; n++) {
                var j = neighbors[n];
                if (j < 0 || j >= w * h || visited[j] || !edges[j]) continue;
                if (Math.abs((j % w) - (i % w)) > 1) continue;
                visited[j] = 1;
                stack.push(j);
            }
        }
        return points;
    }

    function quadFromPoints(points, w, h) {
        if (points.length < 20) return null;
        var minX = w;
        var minY = h;
        var maxX = 0;
        var maxY = 0;
        var tl = points[0];
        var tr = points[0];
        var br = points[0];
        var bl = points[0];
        var tlScore = Infinity;
        var trScore = -Infinity;
        var brScore = -Infinity;
        var blScore = Infinity;
        points.forEach(function (p) {
            minX = Math.min(minX, p.x);
            minY = Math.min(minY, p.y);
            maxX = Math.max(maxX, p.x);
            maxY = Math.max(maxY, p.y);
            var s1 = p.x + p.y;
            var s2 = p.x - p.y;
            if (s1 < tlScore) {
                tlScore = s1;
                tl = p;
            }
            if (s2 > trScore) {
                trScore = s2;
                tr = p;
            }
            if (s1 > brScore) {
                brScore = s1;
                br = p;
            }
            if (s2 < blScore) {
                blScore = s2;
                bl = p;
            }
        });
        var area = (maxX - minX) * (maxY - minY);
        if (area < w * h * 0.08) return null;
        return [tl, tr, br, bl];
    }

    function polygonArea(pts) {
        var sum = 0;
        for (var i = 0; i < pts.length; i++) {
            var a = pts[i];
            var b = pts[(i + 1) % pts.length];
            sum += a.x * b.y - b.x * a.y;
        }
        return Math.abs(sum) / 2;
    }

    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }

    function solveHomography(src, dst) {
        var A = [];
        var b = [];
        var i;
        for (i = 0; i < 4; i++) {
            var x = src[i].x;
            var y = src[i].y;
            var u = dst[i].x;
            var v = dst[i].y;
            A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]);
            b.push(u);
            A.push([0, 0, 0, x, y, 1, -v * x, -v * y]);
            b.push(v);
        }
        var h = gaussSolve(A, b);
        if (!h) return null;
        h.push(1);
        return h;
    }

    function gaussSolve(A, b) {
        var n = b.length;
        var M = [];
        var i;
        var j;
        var k;
        for (i = 0; i < n; i++) {
            M[i] = A[i].slice();
            M[i].push(b[i]);
        }
        for (i = 0; i < n; i++) {
            var maxRow = i;
            for (k = i + 1; k < n; k++) {
                if (Math.abs(M[k][i]) > Math.abs(M[maxRow][i])) maxRow = k;
            }
            var tmp = M[i];
            M[i] = M[maxRow];
            M[maxRow] = tmp;
            if (Math.abs(M[i][i]) < 1e-8) return null;
            var pivot = M[i][i];
            for (j = i; j <= n; j++) M[i][j] /= pivot;
            for (k = 0; k < n; k++) {
                if (k === i) continue;
                var f = M[k][i];
                for (j = i; j <= n; j++) M[k][j] -= f * M[i][j];
            }
        }
        var x = [];
        for (i = 0; i < n; i++) x[i] = M[i][n];
        return x;
    }

    function applyHomography(h, x, y) {
        var w = h[6] * x + h[7] * y + h[8];
        return {
            x: (h[0] * x + h[1] * y + h[2]) / w,
            y: (h[3] * x + h[4] * y + h[5]) / w
        };
    }

    function warpPerspective(source, srcCorners) {
        var ordered = orderCorners(srcCorners);
        var widthTop = distance(ordered[0], ordered[1]);
        var widthBottom = distance(ordered[3], ordered[2]);
        var heightLeft = distance(ordered[0], ordered[3]);
        var heightRight = distance(ordered[1], ordered[2]);
        var outW = Math.max(widthTop, widthBottom);
        var outH = Math.max(heightLeft, heightRight);
        var maxDim = 1600;
        var scale = Math.min(1, maxDim / Math.max(outW, outH));
        outW = Math.max(200, Math.round(outW * scale));
        outH = Math.max(280, Math.round(outH * scale));

        var dest = [
            { x: 0, y: 0 },
            { x: outW - 1, y: 0 },
            { x: outW - 1, y: outH - 1 },
            { x: 0, y: outH - 1 }
        ];
        var h = solveHomography(dest, ordered);
        if (!h) {
            var fallback = document.createElement('canvas');
            fallback.width = source.width;
            fallback.height = source.height;
            fallback.getContext('2d').drawImage(source, 0, 0);
            return fallback;
        }

        var srcCtx = source.getContext('2d', { willReadFrequently: true });
        var srcData = srcCtx.getImageData(0, 0, source.width, source.height);
        var out = document.createElement('canvas');
        out.width = outW;
        out.height = outH;
        var outCtx = out.getContext('2d');
        var outImg = outCtx.createImageData(outW, outH);
        var sW = source.width;
        var sH = source.height;
        var x;
        var y;
        for (y = 0; y < outH; y++) {
            for (x = 0; x < outW; x++) {
                var p = applyHomography(h, x, y);
                var sample = bilinear(srcData.data, sW, sH, p.x, p.y);
                var o = (y * outW + x) * 4;
                outImg.data[o] = sample[0];
                outImg.data[o + 1] = sample[1];
                outImg.data[o + 2] = sample[2];
                outImg.data[o + 3] = 255;
            }
        }
        outCtx.putImageData(outImg, 0, 0);
        return out;
    }

    function distance(a, b) {
        var dx = a.x - b.x;
        var dy = a.y - b.y;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function bilinear(data, w, h, x, y) {
        if (x < 0 || y < 0 || x >= w - 1 || y >= h - 1) {
            var cx = clamp(x, 0, w - 1);
            var cy = clamp(y, 0, h - 1);
            var i = ((cy | 0) * w + (cx | 0)) * 4;
            return [data[i], data[i + 1], data[i + 2]];
        }
        var x0 = Math.floor(x);
        var y0 = Math.floor(y);
        var x1 = x0 + 1;
        var y1 = y0 + 1;
        var xs = x - x0;
        var ys = y - y0;
        var i00 = (y0 * w + x0) * 4;
        var i10 = (y0 * w + x1) * 4;
        var i01 = (y1 * w + x0) * 4;
        var i11 = (y1 * w + x1) * 4;
        var c = [0, 0, 0];
        for (var k = 0; k < 3; k++) {
            var v0 = data[i00 + k] * (1 - xs) + data[i10 + k] * xs;
            var v1 = data[i01 + k] * (1 - xs) + data[i11 + k] * xs;
            c[k] = v0 * (1 - ys) + v1 * ys;
        }
        return c;
    }

    function applyFilter(source, filter, brightness) {
        var canvas = cloneCanvas(source);
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var d = img.data;
        var factor = (brightness - 50) / 50;
        var i;
        for (i = 0; i < d.length; i += 4) {
            var r = d[i];
            var g = d[i + 1];
            var b = d[i + 2];
            if (filter === 'color') {
                r = clamp((r - 128) * 1.18 + 128, 0, 255);
                g = clamp((g - 128) * 1.18 + 128, 0, 255);
                b = clamp((b - 128) * 1.18 + 128, 0, 255);
            }
            var gray = r * 0.299 + g * 0.587 + b * 0.114;
            if (filter === 'gray' || filter === 'bw') {
                r = g = b = gray;
            }
            r = clamp(r + factor * 48, 0, 255);
            g = clamp(g + factor * 48, 0, 255);
            b = clamp(b + factor * 48, 0, 255);
            d[i] = r;
            d[i + 1] = g;
            d[i + 2] = b;
        }
        if (filter === 'bw') {
            adaptiveBw(d, canvas.width, canvas.height);
        }
        ctx.putImageData(img, 0, 0);
        return canvas;
    }

    function adaptiveBw(data, w, h) {
        var n = w * h;
        var gray = new Uint8Array(n);
        var i;
        for (i = 0; i < n; i++) {
            gray[i] = data[i * 4];
        }
        var blurred = boxBlur(gray, w, h, 6);
        for (i = 0; i < n; i++) {
            var v = gray[i] < blurred[i] * 0.96 - 4 ? 0 : 255;
            var o = i * 4;
            data[o] = data[o + 1] = data[o + 2] = v;
        }
    }

    function fitCanvas(canvas, target, pad) {
        pad = pad || 0;
        var ctx = target.getContext('2d');
        var maxW = target.clientWidth || target.width;
        var maxH = target.clientHeight || target.height;
        if (!maxW || !maxH) {
            maxW = window.innerWidth;
            maxH = window.innerHeight * 0.62;
        }
        var scale = Math.min((maxW - pad) / canvas.width, (maxH - pad) / canvas.height);
        target.width = Math.max(1, Math.round(canvas.width * scale));
        target.height = Math.max(1, Math.round(canvas.height * scale));
        ctx.fillStyle = '#111827';
        ctx.fillRect(0, 0, target.width, target.height);
        ctx.drawImage(canvas, 0, 0, target.width, target.height);
        return scale;
    }

    function drawCrop() {
        if (!captureSource) return;
        var scale = fitCanvas(captureSource, cropCanvas, 8);
        var ctx = cropCanvas.getContext('2d');
        ctx.strokeStyle = '#3b82f6';
        ctx.lineWidth = 3;
        ctx.beginPath();
        corners.forEach(function (c, i) {
            var x = c.x * scale;
            var y = c.y * scale;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.closePath();
        ctx.stroke();
        ctx.fillStyle = 'rgba(59,130,246,0.18)';
        ctx.fill();
        corners.forEach(function (c) {
            ctx.beginPath();
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#2563eb';
            ctx.lineWidth = 3;
            ctx.arc(c.x * scale, c.y * scale, 12, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        });
        cropCanvas.dataset.scale = String(scale);
    }

    function hitCorner(ev) {
        var rect = cropCanvas.getBoundingClientRect();
        var scaleX = cropCanvas.width / rect.width;
        var scaleY = cropCanvas.height / rect.height;
        var x = (ev.clientX - rect.left) * scaleX;
        var y = (ev.clientY - rect.top) * scaleY;
        var scale = parseFloat(cropCanvas.dataset.scale || '1');
        var best = -1;
        var bestDist = 28;
        corners.forEach(function (c, i) {
            var dx = c.x * scale - x;
            var dy = c.y * scale - y;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < bestDist) {
                bestDist = dist;
                best = i;
            }
        });
        return { index: best, x: x / scale, y: y / scale };
    }

    async function startCamera() {
        stopCamera();
        setStep('capture');
        showStatus('Ouverture de la caméra…');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showStatus('Caméra indisponible. Importez une photo depuis la galerie.', 'error');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            });
            video.srcObject = stream;
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            await video.play();
            showStatus('Placez le document dans le cadre. Détection automatique activée.');
            liveTimer = setInterval(updateLiveDetection, 280);
            resizeLiveOverlay();
        } catch (err) {
            showStatus('Accès caméra refusé. Utilisez la galerie ou autorisez la caméra.', 'error');
        }
    }

    function resizeLiveOverlay() {
        if (!video || !liveCanvas) return;
        var rect = video.getBoundingClientRect();
        liveCanvas.width = Math.max(1, Math.round(rect.width));
        liveCanvas.height = Math.max(1, Math.round(rect.height));
    }

    function updateLiveDetection() {
        if (!video || video.readyState < 2) return;
        var frame = canvasFromImage(video, 640);
        liveQuad = detectDocumentCorners(frame);
        drawLiveOverlay(frame);
    }

    function drawLiveOverlay(frame) {
        resizeLiveOverlay();
        var ctx = liveCanvas.getContext('2d');
        ctx.clearRect(0, 0, liveCanvas.width, liveCanvas.height);
        var scaleX = liveCanvas.width / frame.width;
        var scaleY = liveCanvas.height / frame.height;
        ctx.lineWidth = 3;
        ctx.strokeStyle = liveQuad ? '#60a5fa' : 'rgba(255,255,255,0.55)';
        ctx.setLineDash(liveQuad ? [] : [10, 8]);
        ctx.beginPath();
        var pts = liveQuad || defaultCorners(frame.width, frame.height);
        pts.forEach(function (p, i) {
            var x = p.x * scaleX;
            var y = p.y * scaleY;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.closePath();
        ctx.stroke();
        if (liveQuad) {
            ctx.fillStyle = 'rgba(59,130,246,0.12)';
            ctx.fill();
        }
    }

    function openCropFromSource(sourceCanvas) {
        stopCamera();
        captureSource = sourceCanvas;
        corners = detectDocumentCorners(sourceCanvas) || defaultCorners(sourceCanvas.width, sourceCanvas.height);
        setStep('crop');
        showStatus('Ajustez les coins si besoin, puis validez.');
        requestAnimationFrame(drawCrop);
    }

    function captureFrame() {
        if (!video || video.readyState < 2) {
            showStatus('Caméra pas encore prête.', 'error');
            return;
        }
        var frame = canvasFromImage(video, 1800);
        openCropFromSource(frame);
    }

    function acceptCrop() {
        if (!captureSource) return;
        var warped = warpPerspective(captureSource, corners);
        pages.push({
            canvas: warped,
            filter: 'color',
            brightness: 55
        });
        captureSource = null;
        renderPages();
        setStep('pages');
        showStatus(pages.length + ' page' + (pages.length > 1 ? 's' : '') + ' dans le document.');
    }

    function renderPages() {
        pagesList.innerHTML = '';
        pages.forEach(function (page, index) {
            var item = document.createElement('div');
            item.className = 'lm-scan-thumb';
            item.draggable = true;
            item.dataset.index = String(index);
            var img = document.createElement('img');
            img.alt = 'Page ' + (index + 1);
            img.src = page.canvas.toDataURL('image/jpeg', 0.6);
            var meta = document.createElement('div');
            meta.className = 'lm-scan-thumb-meta';
            meta.innerHTML =
                '<span>Page ' +
                (index + 1) +
                '</span>' +
                '<span class="lm-scan-thumb-actions">' +
                '<button type="button" data-page-up="' + index + '">↑</button>' +
                '<button type="button" data-page-down="' + index + '">↓</button>' +
                '<button type="button" data-page-rot="' + index + '">⟳</button>' +
                '<button type="button" data-page-del="' + index + '">✕</button>' +
                '</span>';
            item.appendChild(img);
            item.appendChild(meta);
            pagesList.appendChild(item);
        });
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'lm-scan-add-page';
        add.setAttribute('data-scan-add-page', '1');
        add.textContent = '+ Ajouter une page';
        pagesList.appendChild(add);
    }

    function movePage(index, delta) {
        var next = index + delta;
        if (next < 0 || next >= pages.length) return;
        var tmp = pages[index];
        pages[index] = pages[next];
        pages[next] = tmp;
        renderPages();
    }

    function renderEnhance() {
        if (!pages.length) return;
        enhanceIndex = clamp(enhanceIndex, 0, pages.length - 1);
        var page = pages[enhanceIndex];
        var preview = applyFilter(page.canvas, page.filter, page.brightness);
        fitCanvas(preview, enhanceCanvas, 12);
        root.querySelectorAll('[data-scan-filter]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-scan-filter') === page.filter);
        });
        if (brightnessInput) brightnessInput.value = String(page.brightness);
        var label = document.getElementById('lm-scan-enhance-label');
        if (label) label.textContent = 'Page ' + (enhanceIndex + 1) + ' / ' + pages.length;
    }

    function canvasToJpegBytes(canvas, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(
                function (blob) {
                    blob.arrayBuffer().then(function (buffer) {
                        resolve(new Uint8Array(buffer));
                    });
                },
                'image/jpeg',
                quality
            );
        });
    }

    function buildPdf(images) {
        var encoder = new TextEncoder();
        var parts = [];
        var offsets = [0];
        var pos = 0;

        function addBytes(bytes) {
            parts.push(bytes);
            pos += bytes.length;
        }
        function add(str) {
            addBytes(encoder.encode(str));
        }

        add('%PDF-1.4\n');
        var pageWidth = 595.28;
        var pageHeight = 841.89;
        var objectCount = 2 + images.length * 3;
        var catalogId = 1;
        var pagesId = 2;

        function mark() {
            offsets.push(pos);
        }

        mark();
        add('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
        mark();

        var pageIds = images.map(function (_, i) {
            return 3 + i * 3;
        });
        add(
            '2 0 obj\n<< /Type /Pages /Count ' +
                images.length +
                ' /Kids [' +
                pageIds
                    .map(function (id) {
                        return id + ' 0 R';
                    })
                    .join(' ') +
                '] >>\nendobj\n'
        );

        images.forEach(function (image, i) {
            var pageId = 3 + i * 3;
            var contentId = pageId + 1;
            var imageId = pageId + 2;
            var fit = fitToA4(image.width, image.height, pageWidth, pageHeight);
            var content =
                'q\n' +
                fit.w.toFixed(2) +
                ' 0 0 ' +
                fit.h.toFixed(2) +
                ' ' +
                fit.x.toFixed(2) +
                ' ' +
                fit.y.toFixed(2) +
                ' cm\n/Im' +
                i +
                ' Do\nQ\n';
            mark();
            add(
                pageId +
                    ' 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' +
                    pageWidth +
                    ' ' +
                    pageHeight +
                    '] /Contents ' +
                    contentId +
                    ' 0 R /Resources << /XObject << /Im' +
                    i +
                    ' ' +
                    imageId +
                    ' 0 R >> >> >>\nendobj\n'
            );
            mark();
            add(
                contentId +
                    ' 0 obj\n<< /Length ' +
                    content.length +
                    ' >>\nstream\n' +
                    content +
                    'endstream\nendobj\n'
            );
            mark();
            add(
                imageId +
                    ' 0 obj\n<< /Type /XObject /Subtype /Image /Width ' +
                    image.width +
                    ' /Height ' +
                    image.height +
                    ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' +
                    image.bytes.length +
                    ' >>\nstream\n'
            );
            addBytes(image.bytes);
            add('endstream\nendobj\n');
        });

        var xrefPos = pos;
        add('xref\n0 ' + (objectCount + 1) + '\n');
        add('0000000000 65535 f \n');
        for (var i = 1; i <= objectCount; i++) {
            add(String(offsets[i]).padStart(10, '0') + ' 00000 n \n');
        }
        add(
            'trailer\n<< /Size ' +
                (objectCount + 1) +
                ' /Root 1 0 R >>\nstartxref\n' +
                xrefPos +
                '\n%%EOF'
        );

        var total = parts.reduce(function (sum, part) {
            return sum + part.length;
        }, 0);
        var out = new Uint8Array(total);
        var offset = 0;
        parts.forEach(function (part) {
            out.set(part, offset);
            offset += part.length;
        });
        return new Blob([out], { type: 'application/pdf' });
    }

    function fitToA4(w, h, pageW, pageH) {
        var margin = 18;
        var maxW = pageW - margin * 2;
        var maxH = pageH - margin * 2;
        var scale = Math.min(maxW / w, maxH / h);
        var dw = w * scale;
        var dh = h * scale;
        return {
            w: dw,
            h: dh,
            x: (pageW - dw) / 2,
            y: (pageH - dh) / 2
        };
    }

    async function savePdf() {
        if (!pages.length || !current) return;
        setStep('saving');
        showStatus('Création du PDF et enregistrement…');
        try {
            var images = [];
            for (var i = 0; i < pages.length; i++) {
                var page = pages[i];
                var rendered = applyFilter(page.canvas, page.filter, page.brightness);
                var bytes = await canvasToJpegBytes(rendered, 0.78);
                images.push({ bytes: bytes, width: rendered.width, height: rendered.height });
            }
            var blob = buildPdf(images);
            var file = new File([blob], 'scan.pdf', { type: 'application/pdf' });
            var form = new FormData();
            form.append('document_file', file, 'scan.pdf');
            form.append('category', current.category);
            form.append('source', 'scan');
            form.append('_token', csrfToken());
            var response = await fetch(current.url, {
                method: 'POST',
                body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                redirect: 'manual'
            });
            if (response.status >= 400) throw new Error('save failed');
            showStatus('PDF rattaché à l’élément. Actualisation…', 'ok');
            window.location.reload();
        } catch (e) {
            setStep('enhance');
            showStatus('Échec de l’enregistrement. Réessayez.', 'error');
        }
    }

    async function openScanner(trigger) {
        if (!isMobileScannerDevice()) {
            return;
        }
        current = {
            type: trigger.dataset.scanType,
            id: trigger.dataset.scanId,
            category: trigger.dataset.scanCategory || 'primary',
            url: trigger.dataset.scanUrl
        };
        pages = [];
        root.classList.remove('hidden');
        document.body.classList.add('lm-scanner-open');
        await startCamera();
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-managed-scan]');
        if (trigger) {
            event.preventDefault();
            openScanner(trigger);
            return;
        }
        if (!root.contains(event.target) && !event.target.closest('#lm-mobile-scanner')) {
            return;
        }
        if (event.target.closest('[data-scan-close]')) {
            closeScanner();
            return;
        }
        if (event.target.closest('[data-scan-shutter]')) {
            captureFrame();
            return;
        }
        if (event.target.closest('[data-scan-gallery]')) {
            galleryInput && galleryInput.click();
            return;
        }
        if (event.target.closest('[data-scan-retake]')) {
            startCamera();
            return;
        }
        if (event.target.closest('[data-scan-rotate-capture]')) {
            if (!captureSource) return;
            captureSource = rotateCanvas(captureSource, 90);
            corners = detectDocumentCorners(captureSource) || defaultCorners(captureSource.width, captureSource.height);
            drawCrop();
            return;
        }
        if (event.target.closest('[data-scan-accept-crop]')) {
            acceptCrop();
            return;
        }
        if (event.target.closest('[data-scan-add-page]')) {
            startCamera();
            return;
        }
        if (event.target.closest('[data-scan-pages-next]')) {
            if (!pages.length) return;
            enhanceIndex = 0;
            setStep('enhance');
            renderEnhance();
            return;
        }
        if (event.target.closest('[data-scan-enhance-prev]') && enhanceIndex > 0) {
            enhanceIndex -= 1;
            renderEnhance();
            return;
        }
        if (event.target.closest('[data-scan-enhance-next]') && enhanceIndex < pages.length - 1) {
            enhanceIndex += 1;
            renderEnhance();
            return;
        }
        var filterBtn = event.target.closest('[data-scan-filter]');
        if (filterBtn && pages.length) {
            var selectedFilter = filterBtn.getAttribute('data-scan-filter');
            pages.forEach(function (page) {
                page.filter = selectedFilter;
            });
            renderEnhance();
            return;
        }
        if (event.target.closest('[data-scan-save]')) {
            savePdf();
            return;
        }
        var del = event.target.closest('[data-page-del]');
        if (del) {
            pages.splice(parseInt(del.getAttribute('data-page-del'), 10), 1);
            renderPages();
            if (!pages.length) startCamera();
            return;
        }
        var rot = event.target.closest('[data-page-rot]');
        if (rot) {
            var ri = parseInt(rot.getAttribute('data-page-rot'), 10);
            pages[ri].canvas = rotateCanvas(pages[ri].canvas, 90);
            renderPages();
            return;
        }
        var up = event.target.closest('[data-page-up]');
        if (up) {
            movePage(parseInt(up.getAttribute('data-page-up'), 10), -1);
            return;
        }
        var down = event.target.closest('[data-page-down]');
        if (down) {
            movePage(parseInt(down.getAttribute('data-page-down'), 10), 1);
        }
    });

    cropCanvas.addEventListener('pointerdown', function (event) {
        var hit = hitCorner(event);
        draggingCorner = hit.index;
        if (draggingCorner >= 0) {
            cropCanvas.setPointerCapture(event.pointerId);
            event.preventDefault();
        }
    });
    cropCanvas.addEventListener('pointermove', function (event) {
        if (draggingCorner < 0) return;
        var rect = cropCanvas.getBoundingClientRect();
        var scale = parseFloat(cropCanvas.dataset.scale || '1');
        var x = ((event.clientX - rect.left) * cropCanvas.width) / rect.width / scale;
        var y = ((event.clientY - rect.top) * cropCanvas.height) / rect.height / scale;
        corners[draggingCorner] = {
            x: clamp(x, 0, captureSource.width - 1),
            y: clamp(y, 0, captureSource.height - 1)
        };
        drawCrop();
    });
    cropCanvas.addEventListener('pointerup', function () {
        draggingCorner = -1;
    });

    if (galleryInput) {
        galleryInput.addEventListener('change', function () {
            var file = galleryInput.files && galleryInput.files[0];
            galleryInput.value = '';
            if (!file) return;
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(img.src);
                var canvas = canvasFromImage(img, 1800);
                openCropFromSource(canvas);
            };
            img.onerror = function () {
                showStatus('Impossible de lire cette image. Essayez un JPG ou un PNG.', 'error');
            };
            img.src = URL.createObjectURL(file);
        });
    }

    if (brightnessInput) {
        brightnessInput.addEventListener('input', function () {
            if (!pages.length) return;
            var value = parseInt(brightnessInput.value, 10);
            pages.forEach(function (page) {
                page.brightness = value;
            });
            renderEnhance();
        });
    }

    window.addEventListener('resize', function () {
        if (step === 'capture') resizeLiveOverlay();
        if (step === 'crop') drawCrop();
        if (step === 'enhance') renderEnhance();
    });
})();
