<?php

namespace App\Http\Controllers;

use App\Services\ShopDataBackup;

class ShopBackupController extends Controller
{
    /** Full copy of the shop's own data, downloadable by the shop owner account only. */
    public function download(ShopDataBackup $backup)
    {
        $user = auth()->user();
        abort_unless(in_array($user->role, ['shop_owner', 'restaurant', 'merchant'], true), 403);

        @set_time_limit(300);

        $result = $backup->createZip((int) $user->id, storage_path('app/shop-backups'));
        $name = 'backup-' . now()->format('Y-m-d-His') . '.zip';

        return response()->download($result['path'], $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
