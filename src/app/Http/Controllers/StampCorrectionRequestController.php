<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StampCorrectionRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->get('tab', 'pending');
        
        $pendingRequests = StampCorrectionRequest::where('user_id', $user->id)
            ->whereNull('approved_at')
            ->with(['attendance', 'user'])
            ->orderBy('request_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        $approvedRequests = StampCorrectionRequest::where('user_id', $user->id)
            ->whereNotNull('approved_at')
            ->with(['attendance', 'user', 'approvedBy'])
            ->orderBy('approved_at', 'desc')
            ->orderBy('request_date', 'desc')
            ->get();
        
        return view('stampCorrectionRequest', [
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'isAdmin' => false,
            'tab' => $tab,
        ]);
    }
}

