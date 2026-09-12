@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\PaymentImport> $paymentImports */
    $paymentImports = collect($paymentImports ?? [])->filter(
        fn ($import) => in_array($import->status, ['pending', 'processing', 'draft', 'failed'], true)
    )->values();
    $showRoute = $showRoute ?? 'sales.payments.import.show';
    $statusRoute = $statusRoute ?? 'sales.payments.import.status';
    $count = $paymentImports->count();
    $noticeItems = $paymentImports->map(function ($import) use ($showRoute) {
        return array_merge($import->statusPayload(), [
            'file_name' => $import->original_filename,
            'url' => route($showRoute, $import),
            'matched_count' => (int) $import->matched_count,
            'ambiguous_count' => (int) $import->ambiguous_count,
            'not_found_count' => (int) $import->not_found_count,
            'error_message' => $import->error_message,
        ]);
    })->values();
    $statusUrls = $paymentImports->mapWithKeys(
        fn ($import) => [$import->id => route($statusRoute, $import)]
    )->all();
@endphp

@if($count > 0)
<div class="relative"
     x-data="paymentImportsNotice(@js($noticeItems), @js($statusUrls))"
     x-init="boot()"
     @keydown.escape.window="open = false">
    <button type="button"
            @click="open = !open"
            class="relative inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition"
            title="Imports de règlements"
            aria-label="Notifications imports">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span class="absolute -top-1.5 -right-1.5 min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
              x-text="items.length">{{ $count }}</span>
    </button>

    <div x-show="open"
         x-cloak
         @click.outside="open = false"
         class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white shadow-lg z-40 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-900">Imports de règlements</p>
                <p class="text-xs text-gray-500">Fichiers en cours ou à contrôler</p>
            </div>
            <span class="text-xs text-gray-500" x-text="items.length + ' notif.'"></span>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
            <template x-for="item in items" :key="item.id">
                <a :href="item.url" class="block px-4 py-3 hover:bg-gray-50 transition">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 shrink-0">
                            <template x-if="item.status === 'pending' || item.status === 'processing'">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent"></div>
                            </template>
                            <template x-if="item.status === 'draft'">
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            </template>
                            <template x-if="item.status === 'failed'">
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate" x-text="item.file_name"></p>
                            <p class="text-xs mt-0.5"
                               :class="{
                                   'text-blue-700': item.status === 'pending' || item.status === 'processing',
                                   'text-amber-700': item.status === 'draft',
                                   'text-red-700': item.status === 'failed'
                               }"
                               x-text="subtitle(item)"></p>
                        </div>
                    </div>
                </a>
            </template>
        </div>
    </div>
</div>

<script>
function paymentImportsNotice(initialItems, statusUrls) {
    return {
        open: false,
        items: initialItems || [],
        statusUrls: statusUrls || {},
        timer: null,
        boot() {
            if (this.items.some((s) => s.status === 'pending' || s.status === 'processing')) {
                this.timer = setInterval(() => this.pollAll(), 3000);
            }
        },
        subtitle(item) {
            if (item.status === 'pending' || item.status === 'processing') {
                const processed = item.processed_rows || 0;
                const total = item.total_rows || 0;
                const progress = item.progress || 0;
                if (total > 0) return 'Analyse ' + processed + ' / ' + total + ' · ' + progress + '%';
                return item.status === 'pending' ? 'En file d’attente…' : 'Analyse en cours…';
            }
            if (item.status === 'draft') {
                return 'Prêt à contrôler · ' + (item.matched_count || 0) + ' OK · ' + (item.ambiguous_count || 0) + ' ambiguës';
            }
            if (item.status === 'failed') {
                return item.error_message ? String(item.error_message).slice(0, 80) : 'Échec d’analyse';
            }
            return item.label || item.status;
        },
        async pollAll() {
            let reload = false;
            let still = false;
            const next = [...this.items];
            await Promise.all(next.map(async (item, index) => {
                if (item.status !== 'pending' && item.status !== 'processing') return;
                const url = this.statusUrls[item.id];
                if (!url) return;
                try {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    next[index] = { ...item, ...data };
                    if (data.ready || data.failed) reload = true;
                    else still = true;
                } catch (e) {
                    still = true;
                }
            }));
            this.items = next;
            if (reload) {
                window.location.reload();
                return;
            }
            if (!still && this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
    };
}
</script>
@endif
