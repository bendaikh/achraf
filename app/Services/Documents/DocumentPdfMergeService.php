<?php

namespace App\Services\Documents;

use App\Models\ManagedDocument;
use Imagick;
use ImagickException;
use RuntimeException;

class DocumentPdfMergeService
{
    /**
     * Merge managed documents into a single PDF ordered as provided.
     *
     * @param  iterable<int, ManagedDocument>  $documents
     */
    public function merge(iterable $documents): string
    {
        $merged = new Imagick;
        $merged->setResolution(150, 150);
        $added = 0;

        foreach ($documents as $document) {
            $version = $document->currentVersion;
            if (! $version || ! $version->existsOnDisk()) {
                continue;
            }

            $absolute = $version->absolutePath();
            $mime = strtolower((string) $version->mime_type);
            $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

            try {
                if ($this->isImage($mime, $extension)) {
                    $this->appendImageAsPdfPage($merged, $absolute);
                    $added++;
                    continue;
                }

                if ($this->isPdf($mime, $extension)) {
                    $this->appendPdf($merged, $absolute);
                    $added++;
                    continue;
                }

                // Unknown binary: try image then PDF.
                try {
                    $this->appendImageAsPdfPage($merged, $absolute);
                    $added++;
                } catch (ImagickException) {
                    $this->appendPdf($merged, $absolute);
                    $added++;
                }
            } catch (ImagickException $e) {
                throw new RuntimeException(
                    "Impossible d'intégrer le document {$document->display_name}: {$e->getMessage()}",
                    previous: $e
                );
            }
        }

        if ($added === 0) {
            throw new RuntimeException('Aucun document exploitable pour le PDF fusionné.');
        }

        $merged->setImageFormat('pdf');
        $tmp = tempnam(sys_get_temp_dir(), 'libromart-merge-');
        $pdfPath = $tmp.'.pdf';
        @unlink($tmp);

        $merged->writeImages($pdfPath, true);
        $merged->clear();
        $merged->destroy();

        $content = file_get_contents($pdfPath);
        @unlink($pdfPath);

        if ($content === false || $content === '') {
            throw new RuntimeException('Échec de génération du PDF fusionné.');
        }

        return $content;
    }

    protected function appendImageAsPdfPage(Imagick $merged, string $absolutePath): void
    {
        $image = new Imagick($absolutePath);
        $image->setImageFormat('pdf');
        $image->setImageCompressionQuality(85);
        $merged->addImage($image);
        $image->clear();
        $image->destroy();
    }

    protected function appendPdf(Imagick $merged, string $absolutePath): void
    {
        $pdf = new Imagick;
        $pdf->setResolution(150, 150);
        $pdf->readImage($absolutePath);

        foreach ($pdf as $page) {
            $page->setImageFormat('pdf');
            $merged->addImage($page);
        }

        $pdf->clear();
        $pdf->destroy();
    }

    protected function isImage(string $mime, string $extension): bool
    {
        return str_starts_with($mime, 'image/')
            || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    protected function isPdf(string $mime, string $extension): bool
    {
        return $mime === 'application/pdf' || $extension === 'pdf';
    }
}
