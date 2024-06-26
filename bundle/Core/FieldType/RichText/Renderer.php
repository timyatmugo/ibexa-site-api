<?php

declare(strict_types=1);

namespace Netgen\Bundle\IbexaSiteApiBundle\Core\FieldType\RichText;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\FieldTypeRichText\RichText\Renderer as CoreRenderer;
use Psr\Log\LoggerInterface;
use Twig\Environment;

use function sprintf;

class Renderer extends CoreRenderer
{
    private string $ngEmbedConfigurationNamespace;

    public function __construct(
        Repository $repository,
        ConfigResolverInterface $configResolver,
        Environment $templateEngine,
        PermissionResolver $permissionResolver,
        string $tagConfigurationNamespace,
        string $styleConfigurationNamespace,
        string $embedConfigurationNamespace,
        string $ngEmbedConfigurationNamespace,
        ?LoggerInterface $logger = null,
        array $customTagsConfiguration = [],
        array $customStylesConfiguration = []
    ) {
        parent::__construct(
            $repository,
            $configResolver,
            $templateEngine,
            $permissionResolver,
            $tagConfigurationNamespace,
            $styleConfigurationNamespace,
            $embedConfigurationNamespace,
            $logger,
            $customTagsConfiguration,
            $customStylesConfiguration,
        );

        $this->ngEmbedConfigurationNamespace = $ngEmbedConfigurationNamespace;
    }

    protected function getEmbedTemplateName($resourceType, $isInline, $isDenied): ?string
    {
        $configurationReference = $this->getConfigurationReference();

        if ($resourceType === static::RESOURCE_TYPE_CONTENT) {
            $configurationReference .= '.content';
        } else {
            $configurationReference .= '.location';
        }

        if ($isInline) {
            $configurationReference .= '_inline';
        }

        if ($isDenied) {
            $configurationReference .= '_denied';
        }

        if ($this->configResolver->hasParameter($configurationReference)) {
            $configuration = $this->configResolver->getParameter($configurationReference);

            return $configuration['template'];
        }

        $this->logger->warning(
            sprintf("Embed tag configuration '%s' was not found", $configurationReference),
        );

        $configurationReference = $this->getConfigurationReference();

        $configurationReference .= '.default';

        if ($isInline) {
            $configurationReference .= '_inline';
        }

        if ($this->configResolver->hasParameter($configurationReference)) {
            $configuration = $this->configResolver->getParameter($configurationReference);

            return $configuration['template'];
        }

        $this->logger->warning(
            sprintf("Embed tag default configuration '%s' was not found", $configurationReference),
        );

        return null;
    }

    private function getConfigurationReference(): string
    {
        /** @var bool $isSiteApiPrimaryContentView */
        $isSiteApiPrimaryContentView = $this->configResolver->getParameter(
            'ng_site_api.site_api_is_primary_content_view'
        );

        if ($isSiteApiPrimaryContentView) {
            return $this->ngEmbedConfigurationNamespace;
        }

        return $this->embedConfigurationNamespace;
    }
}
