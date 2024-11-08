<?php

declare(strict_types=1);

namespace Drupal\lb_immutable_sections;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\lb_immutable_sections\Plugin\SectionStorage\ImmutableSectionStorage;
use Drupal\navigation\Plugin\SectionStorage\NavigationSectionStorage;

/**
 * Defines a class for render element callbacks.
 *
 * @internal
 */
final class RenderCallbacks {

  /**
   * Pre-render callback for layout builder.
   */
  #[TrustedCallback]
  public static function alterLayoutBuilder(array $element): array {
    if ($element['#section_storage'] instanceof ImmutableSectionStorage) {
      foreach ($element['layout_builder'] as $key => $item) {
        if(!is_array($item) || !isset($item['layout-builder__section'])) {
          continue;
        }
        if ($item['layout-builder__section']['#settings']['immutable'] ?? FALSE) {
          unset($element['layout_builder'][$key]['remove'], $element['layout_builder'][$key]['configure']);
          $element['layout_builder'][$key] = array_merge(
            ['heading' => ['#markup' => $item['layout-builder__section']['#settings']['label'] . ' (Immutable)']],
            $element['layout_builder'][$key]
          );
          unset($element['layout_builder'][$key]['layout-builder__section']['content']['layout_builder_add_block']);
          // @todo Find the  js-layout-builder-region class properly rather than assuming it's the second class.
          unset($element['layout_builder'][$key]['layout-builder__section']['content']['#attributes']['class'][1]);
          foreach (Element::children($element['layout_builder'][$key]['layout-builder__section']['content']) as $delta) {
            unset($element['layout_builder'][$key]['layout-builder__section']['content'][$delta]['#contextual_links']);
          }
        }

        if ($item['layout-builder__section']['#settings']['prevent_sections_before'] ?? FALSE) {
          unset($element['layout_builder'][$key - 1]);
        }

        if ($item['layout-builder__section']['#settings']['prevent_sections_after'] ?? FALSE) {
          unset($element['layout_builder'][$key + 1]);
        }

        if ($item['layout-builder__section']['#settings']['immutable_regions'] ?? FALSE) {
          foreach ($item['layout-builder__section']['#settings']['immutable_regions'] as $region) {
            unset($element['layout_builder'][$key]['layout-builder__section'][$region]['layout_builder_add_block']);
          }
        }
      }
    }
    return $element;
  }

}
