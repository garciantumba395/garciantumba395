<?php

namespace Tests\Unit;

use App\Enums\CompanyRole;
use PHPUnit\Framework\TestCase;

class CompanyRoleTest extends TestCase
{
    public function test_only_owner_and_admin_can_manage_company(): void
    {
        $this->assertTrue(CompanyRole::Owner->canManageCompany());
        $this->assertTrue(CompanyRole::Admin->canManageCompany());
        $this->assertFalse(CompanyRole::Member->canManageCompany());
    }

    public function test_labels_are_in_french(): void
    {
        $this->assertSame('Propriétaire', CompanyRole::Owner->label());
        $this->assertSame('Membre', CompanyRole::Member->label());
    }
}
