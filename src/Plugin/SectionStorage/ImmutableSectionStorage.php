<?php

namespace Drupal\lb_immutable_sections\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\layout_builder\Attribute\SectionStorage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines the 'immutable' section storage type.
 */
#[SectionStorage(
  id: "immutable",
  weight: -20,
  context_definitions: [
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      constraints: [
        "EntityHasField" => OverridesSectionStorage::FIELD_NAME,
      ],
    ),
    'view_mode' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("View mode"),
      default_value: "default",
    ),
  ],
  handles_permission_check: TRUE,
)]
class ImmutableSectionStorage extends OverridesSectionStorage {

  protected function getSectionList() {
    $sections = parent::getSectionList();
    \assert($sections instanceof \Drupal\layout_builder\Field\LayoutSectionItemList);
    $hasImmutable = count(array_filter(
      iterator_to_array($sections),
      fn (LayoutSectionItem $sectionItem) => $sectionItem->section->getLayoutSettings()['immutable'] ?? FALSE
    )) > 0;
    if (!$hasImmutable) {
      return $sections;
    }
    $defaultSections = $this->getDefaultSectionStorage()->getSections();
    foreach ($sections as $delta => $sectionItem) {
      \assert($sectionItem instanceof LayoutSectionItem);
      if ($sectionItem->section->getLayoutSettings()['immutable'] ?? FALSE) {
        $defaultSection = array_filter(
          iterator_to_array($defaultSections),
          fn (Section $section) =>
            array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
            array_key_exists('immutable_uuid', $sectionItem->section->getLayoutSettings()) &&
            $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->section?->getLayoutSettings()['immutable_uuid']
        );
        if ($defaultSection) {
          $sectionItem->section = reset($defaultSection);
        }
      }
    }
    return $sections;
  }

}
