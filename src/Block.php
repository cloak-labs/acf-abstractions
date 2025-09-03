<?php

namespace CloakWP\ACF;

use ErrorException;
use CloakWP\Core\Utils;
use Extended\ACF\Fields\Accordion;
use Extended\ACF\Fields\Message;
use Extended\ACF\Key;
use Extended\ACF\Location;
use InvalidArgumentException;

class Block
{
  protected array $fieldGroupSettings = [];
  protected string $blockJsonPath;
  protected string $blockJson;
  protected mixed $parsedBlockJson;
  protected array $args = [];
  protected array $fields = [];
  protected bool $collapsible = true;
  public string $emptyFieldsMessage = '';
  protected bool $isRegistered = false;
  protected bool $useUIFields = false;

  /**
   * @var callable|null $filterValueCallback
   */
  public $filterValueCallback;

  public function __construct(string $blockJsonPath)
  {
    if (file_exists($blockJsonPath)) {
      // retrieve JSON and parse it into PHP object, storing both
      $blockJsonContent = file_get_contents($blockJsonPath);
      $this->blockJsonPath = $blockJsonPath;
      $this->blockJson = $blockJsonContent;

      $parsed = json_decode($blockJsonContent, true);
      if ($parsed !== null) {
        $name = $parsed['name'];
        // ensure block name is prefixed with "acf/" if it doesn't have a prefix (fixes bug related to Block->value filter):
        if ($name && !str_contains($name, '/'))
          $parsed['name'] = 'acf/' . $name;

        $this->parsedBlockJson = $parsed;
      } else {
        // Throw JSON parsing error
        throw new ErrorException("Error parsing block.json file at path '$blockJsonPath'.");
      }
    } else {
      // Throw file not found error
      throw new InvalidArgumentException("Invalid block.json file path provided to Block::make() -- file not found.");
    }
  }

  public static function make(string $blockJsonPath): static
  {
    return new static($blockJsonPath);
  }

  public function fields(array $fields): static
  {
    // Process fields to handle nested arrays
    $flattenedFields = [];
    foreach ($fields as $field) {
      if (is_array($field) && !isset($field['key'])) {
        // If it's a regular array (not an ACF field array with a key), merge its contents
        foreach ($field as $nestedField) {
          $flattenedFields[] = $nestedField;
        }
      } else {
        // Otherwise add the field directly
        $flattenedFields[] = $field;
      }
    }

    if (empty($this->fields)) {
      $this->fields = $flattenedFields;
    } else {
      $this->fields = array_merge($this->fields, $flattenedFields);
    }

    return $this;
  }

  /**
   * Set a callback function to modify the block's final parsed value (assumes you're using the CloakWP\BlockParser to expose your blocks as structured data via the REST API).
   * 
   * @param callable $filterCallback A callback function that receives the block's structured data and returns the modified data.
   * @return $this The current Block instance.
   */
  public function value(callable $filterCallback): static
  {
    $this->filterValueCallback = $filterCallback;

    return $this;
  }

  /**
   * Optionally set an array of block type arguments. Accepts any public property of WP_Block_Type. See WP_Block_Type::__construct() for information on accepted arguments. Default empty array.
   */
  public function args(array $blockTypeArgs): static
  {
    if (empty($this->args)) {
      $this->args = $blockTypeArgs;
    } else {
      $this->args = array_merge($this->args, $blockTypeArgs);
    }

    return $this;
  }

  /**
   * When set to true (default), the block's ACF Fields will be wrapped by an 
   * Accordion field to provide a collapsible, cleaner UI.
   */
  public function collapsible(bool $shouldCollapse): static
  {
    $this->collapsible = $shouldCollapse;

    return $this;
  }

  /**
   * Optionally provide a custom message to display when no ACF Fields are 
   * assigned to the block.
   */
  public function emptyFieldsMessage(string $message): static
  {
    $this->emptyFieldsMessage = $message;

    return $this;
  }

  /**
   * When called, indicates that this block's fields will be managed through the ACF UI
   * rather than programmatically registered. This ensures we don't call register_extended_field_group()
   * when using the ACF UI to manage fields.
   */
  public function fieldsViaUI(): static
  {
    $this->useUIFields = true;
    return $this;
  }

  /**
   * Registers the ACF Block with WP, registers it with the global BlockRegistry singleton, registers its ACF Field Group, and optionally adds a REST API 
   * response filter if previously set via `apiResponse` method.
   * 
   * Note: can only be registered once.
   */
  public function register(): void
  {
    $name = $this->parsedBlockJson['name'];

    if ($this->isRegistered) {
      Utils::log("Warning: trying to register the Block '$name' after it has already been registered.");
    } else {
      // register block with WP
      register_block_type(
        $this->blockJsonPath,
        $this->args
      );

      $block = $this;
      BlockRegistry::getInstance()->addBlock($block);

      // Only register field group if not using UI fields
      if (!$this->useUIFields) {
        $fieldGroupSettings = $this->getFieldGroupSettings();
        add_action('acf/init', function () use ($fieldGroupSettings) {
          register_extended_field_group($fieldGroupSettings);
        });
      }

      // optionally filter this block's BlockParser JSON output (affects REST API, Iframe previews, etc.) with user-provided callback:
      if ($this->filterValueCallback) {
        add_filter("cloakwp/block/name=$name", $this->filterValueCallback, 10, 3);
      }

      $this->isRegistered = true;
    }
  }

  private function getCollapsibleFields(string $accordionLabel): array
  {
    return [
      Accordion::make($accordionLabel)
        ->open()
        ->multiExpand(), // Allow accordion to remain open when other accordions are opened.
      ...$this->fields,
      Accordion::make('Endpoint')
        ->endpoint()
        ->multiExpand(),
    ];
  }

  private function getEmptyFieldsMessage(): Message
  {
    return Message::make($this->parsedBlockJson['title'])
      ->body($this->emptyFieldsMessage);
  }

  public function getFieldGroupSettings(): array
  {
    if (!empty($this->fieldGroupSettings))
      return $this->fieldGroupSettings;

    // get the parsed JSON data from block.json (access specific properties like $blockConfig['name'], $blockConfig['title'], etc.)
    $blockConfig = $this->parsedBlockJson;

    $name = $blockConfig['name'];
    $title = $blockConfig['title'];

    if (!str_starts_with($name, 'acf/'))
      $name = 'acf/' . $name;

    // set this block's name to the name defined in its block.json file
    $this->fieldGroupSettings['name'] = $name;

    // use name from block.json to set $block['location'] (required by Extended ACF)
    $this->fieldGroupSettings['location'] = [Location::where('block', $name)];

    // auto-generate title property (required by Extended ACF) based on block.json's 'title'
    $this->fieldGroupSettings['title'] = $title;

    if (empty($this->fields) && $this->emptyFieldsMessage) {
      $this->fields[] = $this->getEmptyFieldsMessage();
    }

    if ($this->collapsible) {
      $this->fieldGroupSettings['fields'] = $this->getCollapsibleFields(empty($this->fields) ? "$title" : "$title - Fields");
    } else {
      $this->fieldGroupSettings['fields'] = $this->fields;
    }

    /**
     * This 'key' gets used in the 'InnerBlocks' field class to prevent a block from being nested within itself 
     * TODO: we shouldn't use the block.json "title" property to generate the key, as it may change and 
     *      cause lost data. Need to use the "name" property, after the "/" part (eg. acf/cta -> cta), 
     *      as users are unlikely to change this as it would break existing usage of blocks. Need to be
     *      careful with this change, as it could cause lost data on our existing sites.
     */
    $this->fieldGroupSettings['key'] = Key::sanitize('block_' . $title);

    return $this->fieldGroupSettings;
  }

  public function getFields(): array
  {
    return $this->fields;
  }
}
