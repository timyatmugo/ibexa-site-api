<?php

declare(strict_types=1);

namespace Netgen\IbexaSiteApi\Core\Site\Pagination\Pagerfanta;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchResult;
use Netgen\IbexaSearchExtra\Core\Pagination\Pagerfanta\BaseAdapter;
use Netgen\IbexaSiteApi\API\FindService;

/**
 * Pagerfanta adapter performing search using FindService and Repository sudo.
 */
final class SudoFindAdapter extends BaseAdapter
{
    private FindService $findService;
    private Repository $repository;

    public function __construct(
        Query $query,
        FindService $findService,
        Repository $repository
    ) {
        parent::__construct($query);
        $this->findService = $findService;
        $this->repository = $repository;
    }

    protected function executeQuery(Query $query): SearchResult
    {
        if ($query instanceof LocationQuery) {
            return $this->repository->sudo(
                fn () => $this->findService->findLocations($query),
            );
        }

        return $this->repository->sudo(
            fn () => $this->findService->findContent($query),
        );
    }
}
