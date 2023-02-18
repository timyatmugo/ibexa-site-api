<?php

declare(strict_types=1);

namespace Netgen\Bundle\IbexaSiteApiBundle\Core\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Netgen\Bundle\IbexaSiteApiBundle\SiteAccess\Resolver;
use Netgen\IbexaSiteApi\API\LanguageResolver as BaseLanguageResolver;
use Netgen\IbexaSiteApi\API\Settings as BaseSettings;
use Netgen\IbexaSiteApi\Core\Site\Exceptions\TranslationNotMatchedException;

use function in_array;

final class LanguageResolver extends BaseLanguageResolver
{
    private BaseSettings $settings;
    private Resolver $siteaccessResolver;
    private ConfigResolverInterface $configResolver;
    private SiteAccess $currentSiteaccess;

    public function __construct(
        BaseSettings $settings,
        Resolver $siteaccessResolver,
        ConfigResolverInterface $configResolver
    ) {
        $this->settings = $settings;
        $this->siteaccessResolver = $siteaccessResolver;
        $this->configResolver = $configResolver;
    }

    public function setSiteaccess(?SiteAccess $currentSiteAccess = null): void
    {
        $this->currentSiteaccess = $currentSiteAccess;
    }

    public function resolveForPreview(VersionInfo $versionInfo, string $languageCode): string
    {
        if (in_array($languageCode, $versionInfo->languageCodes, true)) {
            return $languageCode;
        }

        throw new TranslationNotMatchedException(
            $versionInfo->contentInfo->id,
            [
                'resolvedFor' => 'PREVIEW',
                'currentSiteaccess' => $this->currentSiteaccess->name,
                'content' => [
                    'id' => $versionInfo->contentInfo->id,
                    'translations' => $versionInfo->languageCodes,
                    'versionNumber' => $versionInfo->versionNo,
                ],
                'givenLanguageCode' => $languageCode,
            ],
        );
    }

    public function resolveByContent(VersionInfo $versionInfo): string
    {
        $siteaccess = $this->siteaccessResolver->resolveByContent($versionInfo->contentInfo);
        $prioritizedLanguages = $this->getPrioritizedLanguages($siteaccess);

        foreach ($prioritizedLanguages as $languageCode) {
            if (in_array($languageCode, $versionInfo->languageCodes, true)) {
                return $languageCode;
            }
        }

        if ($versionInfo->contentInfo->alwaysAvailable && $this->getIsAlwaysAvailable($siteaccess)) {
            return $versionInfo->contentInfo->mainLanguageCode;
        }

        throw new TranslationNotMatchedException(
            $versionInfo->contentInfo->id,
            [
                'resolvedFor' => 'CONTENT',
                'currentSiteaccess' => [
                    'name' => $this->currentSiteaccess->name,
                    'prioritizedLanguages' => $this->settings->prioritizedLanguages,
                    'useAlwaysAvailable' => $this->settings->useAlwaysAvailable,
                ],
                'resolvedSiteaccess' => [
                    'name' => $siteaccess,
                    'prioritizedLanguages' => $prioritizedLanguages,
                    'useAlwaysAvailable' => $this->getIsAlwaysAvailable($siteaccess),
                ],
                'content' => [
                    'id' => $versionInfo->contentInfo->id,
                    'translations' => $versionInfo->languageCodes,
                    'mainTranslation' => $versionInfo->contentInfo->mainLanguageCode,
                    'alwaysAvailable' => $versionInfo->contentInfo->alwaysAvailable,
                ],
            ],
        );
    }

    public function resolveByLocation(Location $location, VersionInfo $versionInfo): string
    {
        $siteaccess = $this->siteaccessResolver->resolveByLocation($location);
        $prioritizedLanguages = $this->getPrioritizedLanguages($siteaccess);

        foreach ($prioritizedLanguages as $languageCode) {
            if (in_array($languageCode, $versionInfo->languageCodes, true)) {
                return $languageCode;
            }
        }

        if ($versionInfo->contentInfo->alwaysAvailable && $this->getIsAlwaysAvailable($siteaccess)) {
            return $versionInfo->contentInfo->mainLanguageCode;
        }

        throw new TranslationNotMatchedException(
            $versionInfo->contentInfo->id,
            [
                'resolvedFor' => 'LOCATION',
                'currentSiteaccess' => [
                    'name' => $this->currentSiteaccess->name,
                    'prioritizedLanguages' => $this->settings->prioritizedLanguages,
                    'useAlwaysAvailable' => $this->settings->useAlwaysAvailable,
                ],
                'resolvedSiteaccess' => [
                    'name' => $siteaccess,
                    'prioritizedLanguages' => $prioritizedLanguages,
                    'useAlwaysAvailable' => $this->getIsAlwaysAvailable($siteaccess),
                ],
                'content' => [
                    'id' => $versionInfo->contentInfo->id,
                    'locationId' => $location->id,
                    'translations' => $versionInfo->languageCodes,
                    'mainTranslation' => $versionInfo->contentInfo->mainLanguageCode,
                    'alwaysAvailable' => $versionInfo->contentInfo->alwaysAvailable,
                ],
            ],
        );
    }

    private function getPrioritizedLanguages(string $siteaccess): array
    {
        return $this->configResolver->getParameter('languages', null, $siteaccess);
    }

    private function getIsAlwaysAvailable(string $siteaccess): bool
    {
        return $this->configResolver->getParameter(
            'ng_site_api.use_always_available_fallback',
            null,
            $siteaccess,
        );
    }
}
