<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    # -------------------------------------------------------------------
    # "Daily Operation" Controls
    # -------------------------------------------------------------------
    public function daily(): Response
    {
        return Inertia::render('Admin/Operations/Daily');
    }


    # -------------------------------------------------------------------
    # "Room Status" Controls
    # -------------------------------------------------------------------
    public function rooms(): Response
    {
        return Inertia::render('Admin/Operations/RoomStatus');
    }
}
