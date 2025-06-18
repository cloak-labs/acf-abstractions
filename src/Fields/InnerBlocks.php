<?php

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\BlockRegistry;
use Extended\ACF\Fields\{Accordion, FlexibleContent, Layout};

/**
 * An ACF field that allows you to nest ACF blocks (that are registered as CloakWP\ACF\Block instances) within eachother. It's an abstraction around ACF's Flexible Content field.
 */
class InnerBlocks extends FlexibleContent
{
    private array $includes = [];
    private array $excludes = [];
    public bool $excludeFromInnerBlocks = true;

    public static function make(string $label, string|null $name = null): static
    {
        $self = new static($label, $name);
        $self->button('Add block');
        return $self;
    }


    public function include(array $includedBlocks): self
    {
        $this->includes = $includedBlocks;
        return $this;
    }

    public function exclude(array $excludedBlocks): self
    {
        $this->excludes = $excludedBlocks;
        return $this;
    }

    private function createLayoutsFromBlocks($parentKey): array
    {
        $blocks = BlockRegistry::getInstance()->getBlocks();
        $final_layouts = [];

        if (!empty($blocks)) {
            // define field filtering function to exclude certain incompatible fields from being nested in an InnerBlock/layout:
            $filterExcludedFields = function ($fields) use (&$filterExcludedFields) {
                $count = -1;
                // Create a new array of filtered fields instead of modifying the original
                $filteredFields = array_filter($fields, function ($field) use (&$count, $fields) {
                    $count++;

                    if (!$field || !is_object($field)) {
                        return false;
                    }

                    if (isset($field->excludeFromInnerBlocks) && $field->excludeFromInnerBlocks === true) {
                        // `excludeFromInnerBlocks` is a static property that can be set on custom fields to exclude them from InnerBlocks -- the InnerBlocks field itself uses this to prevent infinite recursion; TODO: allow InnerBlocks within InnerBlocks but limit nested recursion to a specified depth to prevent infinite loop.
                        return false;
                    }

                    try {
                        $fieldClass = get_class($field);
                    } catch (\Exception $e) {
                        return false;
                    }

                    // Exclude top-level wrapping Accordion fields because the ACF Flexible Content field UI already wraps layouts in accordion:
                    if (($count == 0 || $count == count($fields) - 1) && $fieldClass == Accordion::class) {
                        return false;
                    }

                    return true;
                });

                return array_map(function ($field) use ($filterExcludedFields) {
                    $property = (new \ReflectionClass($field))->getProperty('settings');
                    $property->setAccessible(true);
                    $settings = $field->settings;

                    if (isset($settings['sub_fields'])) {
                        $settings['sub_fields'] = $filterExcludedFields($settings['sub_fields']);
                    }

                    // Clone the field before modifying its settings to avoid mutating the original field object (fixes bug where only one InnerBlocks field can exist)
                    $newField = $field->cloneRecursively();
                    $property->setValue($newField, $settings);
                    return $newField;
                }, $filteredFields);
            };

            foreach ($blocks as $innerBlock) {
                $settings = $innerBlock->getFieldGroupSettings();
                $included = empty($this->includes) || in_array($settings['name'], $this->includes);

                $is_inner_block_same_as_parent_block = $parentKey == $settings['key'] || str_starts_with($parentKey, $settings['key']); // we don't allow nesting a block within itself
                $excluded = in_array($settings['name'], $this->excludes) || $is_inner_block_same_as_parent_block;

                // filter inner blocks based on whether they've been included or excluded
                if ($included && !$excluded) {
                    $ogFields = $settings['fields'];

                    if (is_array($ogFields)) {
                        $newFields = $filterExcludedFields($ogFields);
                    }

                    $fields = $newFields ?? $ogFields;

                    // Make a Flexible Content "layout" using each block's fields
                    $final_layouts[] = Layout::make($settings['title'], $settings['name'])
                        ->layout('block')
                        ->fields($fields);
                }
            }
        }

        return $final_layouts;
    }

    /** @internal */
    public function get(string|null $parentKey = null): array
    {
        $this->settings['layouts'] = $this->createLayoutsFromBlocks($parentKey); // we copied get() from the base Field class just to add this

        $result = parent::get($parentKey);

        // Adjust the ACF formatting of InnerBlocks field values to mimic the standard Block data format: 
        add_filter("acf/format_value/key={$result['key']}", function ($value, $post_id, $field) {
            $formattedInnerBlocksValue = [];
            if (empty($value) || !is_array($value)) return $value;

            foreach ($value as $layout) {
                $name = $layout['acf_fc_layout'];
                unset($layout['acf_fc_layout']);

                $defaultBlock = [
                    'name' => $name,
                    'type' => 'acf',
                    'attrs' => [],
                    'data' => $layout
                ];

                $formattedBlock = apply_filters('cloakwp/block', $defaultBlock, ['name' => $name, 'type' => 'acf']);
                $formattedInnerBlocksValue[] = $formattedBlock;
            }

            return $formattedInnerBlocksValue;
        }, 10, 3);

        return $result;
    }
}
