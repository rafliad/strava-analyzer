<?php

namespace App\Http\Controllers;

use App\Services\PersonalRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PersonalRecordsController extends Controller
{
    public function index(PersonalRecordService $personalRecordService)
    {
        $user = Auth::user();
        $personalRecords = $personalRecordService->calculatePersonalRecords($user);

        return Inertia::render('Profile/PersonalRecords', [
            'personalRecords' => $personalRecords,
        ]);
    }
}
