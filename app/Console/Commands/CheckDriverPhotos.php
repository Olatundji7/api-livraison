<?php

namespace App\Console\Commands;

use App\Models\Deliverer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckDriverPhotos extends Command
{
    protected $signature = 'app:check-driver-photos';
    protected $description = 'Diagnostic : photo enregistrée en base, fichier présent, URL générée';

    public function handle(): int
    {
        $this->info('upload_max_filesize = ' . ini_get('upload_max_filesize') . ' | post_max_size = ' . ini_get('post_max_size'));
        $this->line('(Une photo plus lourde que upload_max_filesize est ignorée par PHP.)');

        $rows = Deliverer::with('user:id,name')->get()->map(fn (Deliverer $d) => [
            $d->id,
            $d->user?->name,
            $d->photo ?: '(vide)',
            $d->photo ? (Storage::disk('public')->exists($d->photo) ? 'oui' : 'NON') : '-',
            $d->photo_url ?? '-',
        ]);

        $this->table(['ID', 'Livreur', 'photo (base)', 'Fichier présent', 'URL'], $rows);

        return self::SUCCESS;
    }
}
