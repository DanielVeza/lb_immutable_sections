<?php

namespace Drupal\lb_immutable_sections\Plugin\SectionStorage;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\Attribute\SectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;

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

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    $sections = parent::getSections();
    // Exist early if there are no immutable sections.
    // @todo this would also need to check if there are any immutable regions.
    $hasImmutable = \count(\array_filter(
        \iterator_to_array($sections),
        fn ($sectionItem) => $sectionItem->getLayoutSettings()['immutable'] ?? FALSE
      )) > 0;
    if (!$hasImmutable) {
      return $sections;
    }
    $defaultSections = $this->getDefaultSectionStorage()->getSections();
    // Merge default sections with the sections from the overridden layout.
    $mergedSections = $this->mergeSections($sections, $defaultSections);
    $sections = [];
    foreach ($mergedSections as $mergedSection) {
      $sections[] = $mergedSection;
    }
    foreach ($sections as $sectionItem) {
      $defaultSection = NULL;
      if ($sectionItem->getLayoutSettings()['immutable'] ?? FALSE) {
        // Find the default section by this sections immutable_uuid.
        $defaultSection = \array_filter(
          \iterator_to_array($defaultSections),
          fn (Section $section) =>
            \array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
            \array_key_exists('immutable_uuid', $sectionItem->getLayoutSettings()) &&
            $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->getLayoutSettings()['immutable_uuid']
        );
        if ($defaultSection) {
          $sectionItem = \reset($defaultSection);
        }
      }
      if ($sectionItem->getLayoutSettings()['immutable_regions'] ?? FALSE) {
        foreach ($sectionItem->getLayoutSettings()['immutable_regions'] as $region) {
          // Find the default section by this sections immutable_uuid.
          // @todo this is the same as above, lets abstract this.
          $defaultSection = \current(\array_filter(
            \iterator_to_array($defaultSections),
            fn (Section $section) =>
              \array_key_exists('immutable_uuid', $section->getLayoutSettings()) &&
              \array_key_exists('immutable_uuid', $sectionItem->getLayoutSettings()) &&
              $section->getLayoutSettings()['immutable_uuid'] === $sectionItem->getLayoutSettings()['immutable_uuid']
          ));
          if (!$defaultSection) {
            continue;
          }
          // Remove all components from the region on the overridden layout.
          foreach ($sectionItem->getComponentsByRegion($region) as $delta => $component) {
            $sectionItem->removeComponent($delta);
          }
          // Add the components from the default layout to the region.
          // We don't need to worry about the order of the components as
          // they should be in the correct order from the default layout.
          foreach ($defaultSection->getComponentsByRegion($region) as $component) {
            $sectionItem->appendComponent($component);
          }
        }
      }
    }
    return $sections;
  }

  /**
   * Merge the sections from the overridden layout with the default layout.
   */
  private function mergeSections(array $layoutSections, array $defaultSections): array {
    if (\count($layoutSections) === \count(\array_filter($defaultSections, fn (Section $section) => $section->getLayoutSettings()['immutable_uuid'] ?? FALSE))) {
      return $layoutSections;
    }
    $mergedLayoutSections = [];
    foreach ($layoutSections as $delta => $layoutSection) {
      $defaultSectionDelta = \array_search(
        $layoutSection->getLayoutSettings()['immutable_uuid'] ?? '',
        \array_map(fn (Section $section) => \array_key_exists('immutable_uuid', $section->getLayoutSettings()) && $section->getLayoutSettings()['immutable_uuid'], $defaultSections)
      );
      $mergedLayoutSections[$delta] = $layoutSection;
      if ($defaultSectionDelta !== FALSE) {
        unset($defaultSections[$defaultSectionDelta]);
      }
    }
    foreach ($defaultSections as $key => $defaultSection) {
      \array_splice($mergedLayoutSections, $key + 1, 0, [$defaultSection]);
    }
    return $mergedLayoutSections;
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    // @todo I believe this can be removed, but I haven't run a test without it
    // yet.
    if ($this->getEntity()->get(OverridesSectionStorage::FIELD_NAME)->isEmpty()) {
      return count($this->getDefaultSectionStorage()->getSections());
    }
    return parent::count();
  }

}
