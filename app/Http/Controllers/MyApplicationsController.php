<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;

class MyApplicationsController extends Controller
{
    // GET /applications — applications made inside this app, with the employer's status.
    public function index(Request $request)
    {
        return view('applications.index', [
            'applications' => JobApplication::with('job:id,title,company,location,url,status')
                ->where('user_id', $request->user()->id)->latest('applied_at')->paginate(20),
        ]);
    }
}
