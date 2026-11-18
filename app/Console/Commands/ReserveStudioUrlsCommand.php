<?php

namespace App\Console\Commands;

use App\Models\ContentUrl;
use App\Services\ContentUrls;
use App\Services\StudioUrls;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReserveStudioUrlsCommand extends Command
{
    protected $signature = 'studios:reserve-urls {--check : Inspect coverage without writing}';

    protected $description = 'Reserve existing Studio URLs without changing studio details';

    public function handle(): int
    {
        return DB::transaction(function () {
            if (! $this->option('check')) {
                app(StudioUrls::class)->lockPublication();
            }
            $query = DB::table('studios')->orderBy('id');
            $studios = ($this->option('check') ? $query : $query->lockForUpdate())->get();
            $missing = 0;
            $unprotected = 0;
            foreach ($studios as $studio) {
                foreach (StudioUrls::paths($studio->slug) as $path) {
                    $owner = ContentUrl::query()->where('path', $path)->first();
                    if ($owner && ($owner->content_type !== ContentUrl::STUDIO || $owner->content_id !== $studio->id)) {
                        throw new \RuntimeException('URL ownership conflict. No reservations were changed.');
                    }
                    if (! $owner) {
                        $missing++;
                        if (! $this->option('check')) {
                            app(ContentUrls::class)->reserve(ContentUrl::STUDIO, $studio->id, $path);
                        }
                    }
                }
                if ($studio->status === 'active' && $studio->url_locked_at === null) {
                    $unprotected++;
                    if (! $this->option('check')) {
                        DB::table('studios')->where('id', $studio->id)->update(['url_locked_at' => now()]);
                    }
                }
            }
            $this->info(count($studios).' studios checked; '.$missing.($this->option('check') ? ' missing reservations.' : ' URLs reserved.'));

            if ($this->option('check') && ($missing || $unprotected)) {
                $this->warn($unprotected.' active studio URLs need protection. Run the approved backfill before enabling URL changes.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        });
    }
}
