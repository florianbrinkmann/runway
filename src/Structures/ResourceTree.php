<?php

namespace StatamicRadPack\Runway\Structures;

use Statamic\Eloquent\Structures\CollectionTree;
use Statamic\Facades\Blink;
use Statamic\Facades\Stache;

class ResourceTree extends CollectionTree
{
    private $structureCache;

    public function structure()
    {
        if ($this->structureCache) {
            return $this->structureCache;
        }

        return $this->structureCache = Blink::once('resource-tree-structure-'.$this->handle(), function () {
            return (new ResourceStructure())
                ->handle($this->handle());
        });
    }

    public function path()
    {
        $path = Stache::store('collection-trees')->directory();

        $handle = $this->handle();

        return "{$path}/runway/{$handle}.yaml";
    }

    protected function dispatchSavedEvent()
    {

    }

    protected function dispatchSavingEvent()
    {

    }

    protected function dispatchDeletedEvent()
    {

    }
}
