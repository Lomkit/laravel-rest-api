<?php

namespace Lomkit\Rest\Console\QuickStart;

enum GuardKind
{
    case Sanctum;
    case Passport;
    case Defined;

    public static function of(string $guard, mixed $definition): ?self
    {
        return match (true) {
            $guard === ApiGuard::SANCTUM => self::Sanctum,
            $guard === ApiGuard::PASSPORT && ($definition === null || (is_array($definition) && ($definition['driver'] ?? null) === 'passport')) => self::Passport,
            $definition !== null => self::Defined,
            default => null,
        };
    }

    public function package(): ?ApiPackage
    {
        return match ($this) {
            self::Sanctum => ApiPackage::Sanctum,
            self::Passport => ApiPackage::Passport,
            self::Defined => null,
        };
    }

    public function installs(): ApiPackage
    {
        return $this->package() ?? ApiPackage::Sanctum;
    }
}
