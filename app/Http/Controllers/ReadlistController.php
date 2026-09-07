<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class ReadlistController extends Controller
{
    public function __invoke(User $user): View
    {
        $readlist = $user->readingLists()
            ->where('status', 'want_to_read')
            ->with(['literature.authors'])
            ->latest('updated_at')
            ->paginate(24);

        return view('readlist.index', compact('user', 'readlist'));
    }
}
