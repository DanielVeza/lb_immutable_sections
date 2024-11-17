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
use Drupal\layout_builder\Field\LayoutSectionItemList;
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
    return parent::getSectionList();
    $sections = parent::getSectionList();
//    if ($this->getEntity()->get(OverridesSectionStorage::FIELD_NAME)->isEmpty()) {
//      return $sections;
//      //return $this->getDefaultSectionStorage()->getSectionList();
//    }
    \assert($sections instanceof LayoutSectionItemList);
    $hasImmutable = count(array_filter(
      iterator_to_array($sections),
      fn (LayoutSectionItem $sectionItem) => $sectionItem->section->getLayoutSettings()['immutable'] ?? FALSE
    )) > 0;
    if (!$hasImmutable) {
      return $sections;
    }
    $layoutSections = $sections->getSections();
    $defaultSections = $this->getDefaultSectionStorage()->getSections();
    $l = strlen(serialize($layoutSections));
    $d = strlen(serialize($defaultSections));
    $mergedSections = $this->mergeSections($layoutSections, $defaultSections);
    $sections->removeAllSections();
    foreach ($mergedSections as $mergedSection) {
      $sections->appendItem($mergedSection);
    }
    $a = 1;
    foreach ($sections as $delta => $sectionItem) {
      $defaultSection = NULL;
      \assert($sectionItem instanceof LayoutSectionItem);
      if ($sectionItem->section->getLayoutSettings()['immutable'] ?? FALSE) {
        $defaultSection = array_filter(
          iterator_to_array($defaultSections),
          fn (Section $section) =>
            array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
            array_key_exists('immutable_uuid', $sectionItem->section?->getLayoutSettings()) &&
            $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->section?->getLayoutSettings()['immutable_uuid']
        );
        if ($defaultSection) {
          $sectionItem->section = reset($defaultSection);
        }
      }
      if ($sectionItem->section->getLayoutSettings()['immutable_regions'] ?? FALSE) {
        foreach ($sectionItem->section->getLayoutSettings()['immutable_regions'] as $region) {
          //if (!isset($defaultSection)) {
            $defaultSection = current(array_filter(
              iterator_to_array($defaultSections),
              fn (Section $section) =>
                array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
                array_key_exists('immutable_uuid', $sectionItem->section?->getLayoutSettings()) &&
                $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->section?->getLayoutSettings()['immutable_uuid']
            ));
          //}
          foreach ($sectionItem->section->getComponentsByRegion($region) as $delta => $component) {
            $sectionItem->section->removeComponent($delta);
          }
          foreach ($defaultSection->getComponentsByRegion($region) as $delta => $component) {
            $sectionItem->section->appendComponent($component);
          }
        }
      }
    }
    return $sections;
  }

  private function mergeSections(array $layoutSections, array $defaultSections): array {
    if (count($layoutSections) === count(array_filter($defaultSections, fn (Section $section) => $section->getLayoutSettings()['immutable_uuid'] ?? FALSE))) {
      return $layoutSections;
    }

    $mergedLayoutSections = [];

    foreach ($layoutSections as $delta => $layoutSection) {
      $defaultSectionDelta = array_search(
        $layoutSection->getLayoutSettings()['immutable_uuid'] ?? '',
        array_map(fn (Section $section) => array_key_exists('immutable_uuid', $section->getLayoutSettings()) && $section->getLayoutSettings()['immutable_uuid'], $defaultSections)
      );
      $mergedLayoutSections[$delta] = $layoutSection;
      if ($defaultSectionDelta !== FALSE) {
        unset($defaultSections[$defaultSectionDelta]);
      }
//      $defaultSection = array_filter(
//        $defaultSections,
//        fn (Section $section) => $section->getLayoutSettings()['immutable_uuid'] === $layoutSection->getLayoutSettings()['immutable_uuid']
//      );
//      if ($defaultSection) {
//        $mergedLayoutSections[$delta] = reset($defaultSection);
//
//      }
//      else {
//        $mergedLayoutSections[$delta] = $layoutSection;
//      }
    }
    foreach ($defaultSections as $key => $defaultSection) {
      array_splice($mergedLayoutSections, $key + 1, 0, [$defaultSection]);
    }

    return $mergedLayoutSections;

  }

  public function count(): int {
    if ($this->getEntity()->get(OverridesSectionStorage::FIELD_NAME)->isEmpty()) {
      return count($this->getDefaultSectionStorage()->getSections());
    }
    return parent::count(); // TODO: Change the autogenerated stub
  }

  public function getSections() {
    $sections = parent::getSections();
    $hasImmutable = count(array_filter(
        iterator_to_array($sections),
        fn ($sectionItem) => $sectionItem->getLayoutSettings()['immutable'] ?? FALSE
      )) > 0;
    if (!$hasImmutable) {
      return $sections;
    }
    $layoutSections = $sections;
    $defaultSections = $this->getDefaultSectionStorage()->getSections();
    $l = strlen(serialize($layoutSections));
    $d = strlen(serialize($defaultSections));
    $mergedSections = $this->mergeSections($layoutSections, $defaultSections);
    $sections = [];
    foreach ($mergedSections as $mergedSection) {
      $sections[] = $mergedSection;
    }
    $a = 1;
    foreach ($sections as $delta => $sectionItem) {
      $defaultSection = NULL;
      //\assert($sectionItem instanceof LayoutSectionItem);
      if ($sectionItem->getLayoutSettings()['immutable'] ?? FALSE) {
        $defaultSection = array_filter(
          iterator_to_array($defaultSections),
          fn (Section $section) =>
            array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
            array_key_exists('immutable_uuid', $sectionItem->getLayoutSettings()) &&
            $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->getLayoutSettings()['immutable_uuid']
        );
        if ($defaultSection) {
          $sectionItem = reset($defaultSection);
        }
      }
      if ($sectionItem->getLayoutSettings()['immutable_regions'] ?? FALSE) {
        foreach ($sectionItem->getLayoutSettings()['immutable_regions'] as $region) {
          //if (!isset($defaultSection)) {
          $defaultSection = current(array_filter(
            iterator_to_array($defaultSections),
            fn (Section $section) =>
              array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
              array_key_exists('immutable_uuid', $sectionItem->getLayoutSettings()) &&
              $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->getLayoutSettings()['immutable_uuid']
          ));
          //}
          foreach ($sectionItem->getComponentsByRegion($region) as $delta => $component) {
            $sectionItem->removeComponent($delta);
          }
          foreach ($defaultSection->getComponentsByRegion($region) as $delta => $component) {
            $sectionItem->appendComponent($component);
          }
        }
      }
    }
    return $sections;
  }

}
