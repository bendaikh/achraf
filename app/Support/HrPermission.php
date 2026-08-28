<?php

namespace App\Support;

use App\Models\User;

class HrPermission
{
    public const VIEW = 'view_hr';

    public const EDIT = 'edit_hr';

    public const VIEW_SALARIES = 'view_salaries';

    public const PREPARE_PAYROLL = 'prepare_payroll';

    public const VALIDATE_PAYROLL = 'validate_payroll';

    public const PAY_PAYROLL = 'pay_payroll';

    public const VIEW_DOCUMENTS = 'view_hr_documents';

    public const MANAGE_SETTINGS = 'manage_hr_settings';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::VIEW => 'Voir RH',
            self::EDIT => 'Modifier RH',
            self::VIEW_SALARIES => 'Voir salaires',
            self::PREPARE_PAYROLL => 'Préparer paie',
            self::VALIDATE_PAYROLL => 'Valider paie',
            self::PAY_PAYROLL => 'Payer paie',
            self::VIEW_DOCUMENTS => 'Voir documents RH',
            self::MANAGE_SETTINGS => 'Gérer paramètres RH',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public static function allows(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasRole('admin')) {
            return true;
        }

        $granted = $user->hr_permissions;
        if ($granted === null) {
            return true;
        }

        return is_array($granted) && in_array($permission, $granted, true);
    }
}
