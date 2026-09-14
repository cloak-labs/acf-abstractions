<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class BlockEnricher
{
  public function __construct(
    private QueryExecutor $executor,
    private QueryArgsBuilder $argsBuilder = new QueryArgsBuilder(),
  ) {
  }

  /**
   * @param array<string, mixed> $parsedBlock
   * @param list<array<string, mixed>>|array<string, array<string, mixed>> $fieldDefinitions
   * @return array<string, mixed>
   */
  public function enrich(array $parsedBlock, array $fieldDefinitions, mixed $block, ?int $postId): array
  {
    $data = is_array($parsedBlock['data'] ?? null) ? $parsedBlock['data'] : [];
    $instances = QueryDiscovery::find($data, $fieldDefinitions);

    foreach ($instances as $instance) {
      $definition = DefinitionRegistry::get($instance['id']);
      if ($definition === null) {
        continue;
      }

      $context = new QueryContext(
        parsedBlock: $parsedBlock,
        field: $instance['field'],
        path: $instance['path'],
        rawValue: $instance['value'],
        postId: $postId,
        wpBlock: $block,
        definition: $definition,
      );

      $request = QueryRequest::from($definition, $instance['value']);
      $posts = $this->resolvePosts($definition, $request, $context);
      $items = $this->mapPosts($definition, $posts);

      $parsedBlock = ResultInjector::inject(
        $parsedBlock,
        $items,
        $instance['path'],
        $definition,
        $context,
      );
    }

    return $parsedBlock;
  }

  /**
   * @return list<object>
   */
  private function resolvePosts(Definition $definition, QueryRequest $request, QueryContext $context): array
  {
    if (is_callable($definition->resolver)) {
      $posts = ($definition->resolver)($request, $context);

      return is_array($posts) ? array_values($posts) : [];
    }

    $args = $this->argsBuilder->compile($request);
    if ($args === null) {
      return [];
    }

    return $this->executor->getPosts($args);
  }

  /**
   * @param list<object> $posts
   * @return list<mixed>
   */
  private function mapPosts(Definition $definition, array $posts): array
  {
    $mapper = is_callable($definition->mapper) ? $definition->mapper : new DefaultPostMapper();
    $items = [];

    foreach ($posts as $post) {
      if (!is_object($post)) {
        continue;
      }
      $items[] = $mapper($post);
    }

    return $items;
  }
}
