<?php

namespace Netgen\EzPlatformSiteApi\Core\Site\Values;

use eZ\Publish\API\Repository\Values\Content\Field as APIField;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ContentId;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalAnd;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Visibility;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause\Location\Path;
use Netgen\EzPlatformSiteApi\API\Values\Content as APIContent;
use Netgen\EzPlatformSiteApi\Core\Site\Pagination\Pagerfanta\LocationSearchFilterAdapter;
use Pagerfanta\Pagerfanta;

final class Content extends APIContent
{
    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\ContentInfo
     */
    protected $contentInfo;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\Field[]
     */
    protected $fields;

    /**
     * @var \eZ\Publish\API\Repository\Values\Content\Content
     */
    protected $innerContent;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\Field[]
     */
    private $fieldsById = [];

    /**
     * @var int
     */
    private $versionNo;

    /**
     * @var \eZ\Publish\API\Repository\Values\ContentType\ContentType
     */
    protected $innerContentType;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Site
     */
    private $site;

    /**
     * @var \eZ\Publish\API\Repository\FieldTypeService
     */
    private $fieldTypeService;

    /**
     * @var \eZ\Publish\API\Repository\ContentService
     */
    private $contentService;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\Location
     */
    private $internalMainLocation;

    public function __construct(array $properties = [])
    {
        if (array_key_exists('versionNo', $properties)) {
            $this->versionNo = $properties['versionNo'];
            unset($properties['versionNo']);
        }

        if (array_key_exists('site', $properties)) {
            $this->site = $properties['site'];
            unset($properties['site']);
        }

        if (array_key_exists('contentService', $properties)) {
            $this->contentService = $properties['contentService'];
            unset($properties['contentService']);
        }

        if (array_key_exists('fieldTypeService', $properties)) {
            $this->fieldTypeService = $properties['fieldTypeService'];
            unset($properties['fieldTypeService']);
        }

        parent::__construct($properties);
    }

    /**
     * {@inheritdoc}
     *
     * Magic getter for retrieving convenience properties.
     *
     * @param string $property The name of the property to retrieve
     *
     * @return mixed
     */
    public function __get($property)
    {
        switch ($property) {
            case 'fields':
                $this->initializeFields();
                return $this->fields;
            case 'id':
                return $this->contentInfo->id;
            case 'name':
                return $this->contentInfo->name;
            case 'mainLocationId':
                return $this->contentInfo->mainLocationId;
            case 'mainLocation':
                return $this->getMainLocation();
            case 'innerContent':
                return $this->getInnerContent();
        }

        if (property_exists($this, $property)) {
            return $this->$property;
        }

        if (property_exists($this->innerContent, $property)) {
            return $this->innerContent->$property;
        }

        return parent::__get($property);
    }

    /**
     * Magic isset for signaling existence of convenience properties.
     *
     * @param string $property
     *
     * @return bool
     */
    public function __isset($property)
    {
        switch ($property) {
            case 'fields':
            case 'id':
            case 'name':
            case 'mainLocationId':
            case 'mainLocation':
            case 'innerContent':
                return true;
        }

        if (property_exists($this, $property) || property_exists($this->innerContent, $property)) {
            return true;
        }

        return parent::__isset($property);
    }

    public function hasField($identifier)
    {
        return isset($this->fields[$identifier]);
    }

    public function getField($identifier)
    {
        if ($this->hasField($identifier)) {
            return $this->fields[$identifier];
        }

        return null;
    }

    public function hasFieldById($id)
    {
        return isset($this->fieldsById[$id]);
    }

    public function getFieldById($id)
    {
        if ($this->hasFieldById($id)) {
            return $this->fieldsById[$id];
        }

        return null;
    }

    public function getFieldValue($identifier)
    {
        if ($this->hasField($identifier)) {
            return $this->fields[$identifier]->value;
        }

        return null;
    }

    public function getFieldValueById($id)
    {
        if ($this->hasFieldById($id)) {
            return $this->fieldsById[$id]->value;
        }

        return null;
    }

    public function getLocations($limit = 25)
    {
        return $this->filterLocations($limit)->getIterator();
    }

    public function filterLocations($maxPerPage = 25, $currentPage = 1)
    {
        $pager = new Pagerfanta(
            new LocationSearchFilterAdapter(
                new LocationQuery([
                    'filter' => new LogicalAnd(
                        [
                            new ContentId($this->id),
                            new Visibility(Visibility::VISIBLE),
                        ]
                    ),
                    'sortClauses' => [
                        new Path(),
                    ],
                ]),
                $this->site->getFilterService()
            )
        );

        $pager->setNormalizeOutOfRangePages(true);
        $pager->setMaxPerPage($maxPerPage);
        $pager->setCurrentPage($currentPage);

        return $pager;
    }

    private function initializeFields()
    {
        if ($this->fields === null) {
            $content = $this->getInnerContent();
            foreach ($content->getFieldsByLanguage($this->contentInfo->languageCode) as $field) {
                $this->buildField($field);
            }
        }
    }

    private function buildField(APIField $apiField)
    {
        $fieldDefinition = $this->innerContentType->getFieldDefinition($apiField->fieldDefIdentifier);
        $fieldTypeIdentifier = $fieldDefinition->fieldTypeIdentifier;
        $isEmpty = $this->fieldTypeService->getFieldType($fieldTypeIdentifier)->isEmptyValue(
            $apiField->value
        );

        $field = new Field([
            'isEmpty' => $isEmpty,
            'innerField' => $apiField,
            'content' => $this,
        ]);

        $this->fields[$field->fieldDefIdentifier] = $field;
        $this->fieldsById[$field->id] = $field;
    }

    private function getMainLocation()
    {
        if ($this->internalMainLocation === null && $this->contentInfo->mainLocationId !== null) {
            $this->internalMainLocation = $this->site->getLoadService()->loadLocation(
                $this->innerContent->contentInfo->mainLocationId
            );
        }

        return $this->internalMainLocation;
    }

    private function getInnerContent()
    {
        if ($this->innerContent === null) {
            $this->innerContent = $this->contentService->loadContent(
                $this->contentInfo->id,
                [$this->contentInfo->languageCode],
                $this->versionNo
            );
        }

        return $this->innerContent;
    }
}
