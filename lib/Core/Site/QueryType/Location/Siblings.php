<?php

declare(strict_types=1);

namespace Netgen\IbexaSiteApi\Core\Site\QueryType\Location;

use Ibexa\Contracts\Core\Repository\Exceptions\NotImplementedException;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LocationId;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalNot;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\ParentLocationId;
use Netgen\IbexaSiteApi\API\Settings;
use Netgen\IbexaSiteApi\API\Values\Location as SiteLocation;
use Netgen\IbexaSiteApi\Core\Site\QueryType\Location;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function sprintf;

/**
 * Siblings Location QueryType.
 *
 * @see \Netgen\IbexaSiteApi\Core\Site\QueryType\Location
 */
final class Siblings extends Location
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(Settings $settings, ?LoggerInterface $logger = null)
    {
        parent::__construct($settings);

        $this->logger = $logger ?? new NullLogger();
    }

    public static function getName(): string
    {
        return 'SiteAPI:Location/Siblings';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @throws \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->remove(['depth', 'parent_location_id', 'subtree']);
        $resolver->setRequired('location');
        $resolver->setAllowedTypes('location', SiteLocation::class);

        $resolver->setDefault(
            'sort',
            function (Options $options): array {
                /** @var \Netgen\IbexaSiteApi\API\Values\Location $location */
                $location = $options['location'];

                try {
                    return $location->parent->getSortClauses();
                } catch (NotImplementedException $exception) {
                    $this->logger->notice(
                        sprintf(
                            'Cannot use sort clauses from parent location: %s',
                            $exception->getMessage()
                        )
                    );

                    return [];
                }
            },
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion[]
     *
     * @throws \InvalidArgumentException
     */
    protected function getFilterCriteria(array $parameters): array
    {
        /** @var \Netgen\IbexaSiteApi\API\Values\Location $location */
        $location = $parameters['location'];

        return [
            new ParentLocationId($location->parentLocationId),
            new LogicalNot(new LocationId($location->id)),
        ];
    }
}
