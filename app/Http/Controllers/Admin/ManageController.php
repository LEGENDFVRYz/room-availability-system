<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ManageController extends Controller
{
    public function rooms(): Response
    {
        return Inertia::render('Admin/Manage/Rooms');
    }

    public function configs(): Response
    {
        return Inertia::render('Admin/Manage/Configs');
    }
}
