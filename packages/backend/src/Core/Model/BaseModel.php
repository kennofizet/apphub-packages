<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Core\Model;

use Kennofizet\PackagesCore\Core\Model\BaseModel as CoreBaseModel;

/**
 * Optional Eloquent base for new App Hub models.
 *
 * Existing models keep Illuminate\Model + UsesAppHubTable because apphub tables
 * use configurable names and omit packages-core zone/season columns.
 */
class BaseModel extends CoreBaseModel
{
}
