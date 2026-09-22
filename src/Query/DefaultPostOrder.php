<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class DefaultPostOrder
{
  /** @var array<string, string> */
  public const ORDERBY = [
    'menu_order' => 'ASC',
    'date' => 'DESC',
  ];

  /**
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  public static function apply(array $args): array
  {
    if (!array_key_exists('orderby', $args) || $args['orderby'] === '' || $args['orderby'] === []) {
      $args['orderby'] = self::ORDERBY;
    }

    return $args;
  }

  /**
   * Keep newest-first as a secondary sort unless the caller already chose
   * date or random ordering.
   *
   * @param array<string, string> $orderby
   * @return array<string, string>
   */
  public static function withDateTiebreaker(array $orderby): array
  {
    if ($orderby === [] || isset($orderby['date']) || isset($orderby['rand'])) {
      return $orderby;
    }

    return $orderby + ['date' => 'DESC'];
  }
}
