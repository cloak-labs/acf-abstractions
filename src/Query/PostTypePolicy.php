<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

enum PostTypePolicy
{
  case Locked;
  case Allowlist;
  case Any;
}
