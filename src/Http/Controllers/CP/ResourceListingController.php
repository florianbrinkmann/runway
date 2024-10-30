<?php

namespace StatamicRadPack\Runway\Http\Controllers\CP;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Builder as BaseStatamicBuilder;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use Statamic\Support\Arr;
use StatamicRadPack\Runway\Http\Resources\CP\Models;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Structures\ResourceStructure;
use StatamicRadPack\Runway\Structures\ResourceTree;

class ResourceListingController extends CpController
{
    use QueriesFilters, Traits\HasListingColumns;

    public function index(FilteredRequest $request, Resource $resource)
    {
        $blueprint = $resource->blueprint();

        if (! User::current()->can('view', $resource)) {
            abort(403);
        }

        $query = $resource->model()->with($resource->eagerLoadingRelationships());

        $query->when($query->hasNamedScope('runwayListing'), fn ($query) => $query->runwayListing());

        $searchQuery = $request->search ?? false;

        $query = $this->applySearch($resource, $query, $searchQuery);

        $query->when(method_exists($query, 'getQuery') && $query->getQuery()->orders, function ($query) use ($request) {
            if ($request->input('sort')) {
                $query->reorder($request->input('sort'), $request->input('order'));
            }
        }, fn ($query) => $query->orderBy($request->input('sort', $resource->orderBy()), $request->input('order', $resource->orderByDirection())));

        $activeFilterBadges = $this->queryFilters($query, $request->filters, [
            'resource' => $resource->handle(),
            'blueprints' => [$blueprint],
        ]);

        $results = $query->paginate($request->input('perPage', config('statamic.cp.pagination_size')));
        // if ($request->input('tree')) {
        //     $results = $query->get();
        // } else {
        //     $results = $query->paginate($request->input('perPage', config('statamic.cp.pagination_size')));
        // }

        // $test = (new ResourceTree())
        //     ->withEntries()
        //     ->handle($resource->handle())
        //     ->locale('default')
        //     ->pages();

        if ($searchQuery && $resource->hasSearchIndex()) {
            $results->setCollection($results->getCollection()->map(fn ($item) => $item->getSearchable()->model()));
        }

        return (new Models($results))
            ->runwayResource($resource)
            ->blueprint($resource->blueprint())
            ->setColumnPreferenceKey("runway.{$resource->handle()}.columns")
            ->additional([
                'meta' => [
                    'activeFilterBadges' => $activeFilterBadges,
                ],
            ]);
    }

    private function applySearch(Resource $resource, Builder $query, string $searchQuery): Builder|BaseStatamicBuilder
    {
        if (! $searchQuery) {
            return $query;
        }

        if ($resource->hasSearchIndex() && ($index = $resource->searchIndex())) {
            return $index->ensureExists()->search($searchQuery);
        }

        return $query->runwaySearch($searchQuery);
    }

    public function update(Request $request, Resource $resource)
    {
        // $this->authorize('reorder', $collection);

        $contents = $this->toTree($request->pages);

        // $structure = (new ResourceStructure())
        //     ->handle($resource->handle());

        $tree = (new ResourceTree())
            ->handle($resource->handle())
            ->locale('default')
            ->model($resource->model())
            ->tree($contents);

        // Clone the tree and add the submitted contents into it so we can
        // validate URI uniqueness without affecting the real object in memory.
        // $this->validateUniqueUris((clone $tree)->disableUriCache()->tree($contents));

        // $this->deleteEntries($request);

        // Validate the tree, which will add any missing entries or throw an exception
        // if somehow the root would end up having child pages, which isn't allowed.
        // $contents = $structure->validateTree($contents, $request->site);

        return [
            'saved' => $tree->tree($contents)->save(),
        ];
    }

    private function toTree($items)
    {
        return collect($items)->map(function ($item) {
            return Arr::removeNullValues([
                'entry' => $ref = $item['id'] ?? null,
                'title' => $ref ? null : ($item['title'] ?? null),
                'url' => $ref ? null : ($item['url'] ?? null),
                'children' => $this->toTree($item['children']),
            ]);
        })->all();
    }
}
