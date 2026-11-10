<?php

namespace Wexample\SymfonyWalletDs\Traits;

use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonyWalletDs\WexampleSymfonyWalletDsBundle;

trait SymfonyWalletDsBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyWalletDsBundle::class;
    }
}
