<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Registered inside the admin group, behind auth. This route reads serial
 * numbers, report references and every name in the school, so it must never be
 * reachable without a password — an unauthenticated search would hand over in
 * one request everything section 13 works to keep off the public pages.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): View
    {
        $term = $request->string('q')->trim()->toString();
        $groups = $search->groups($term);

        // Typing into the header box asks for the panel on its own; pressing
        // Enter, or arriving with no JavaScript at all, gets the whole page.
        $view = $request->header('HX-Request')
            ? 'admin.search._results'
            : 'admin.search.index';

        return view($view, [
            'term' => $term,
            'groups' => $groups,
            'tooShort' => $term !== '' && mb_strlen($term) < GlobalSearch::MIN_TERM_LENGTH,
        ]);
    }
}
