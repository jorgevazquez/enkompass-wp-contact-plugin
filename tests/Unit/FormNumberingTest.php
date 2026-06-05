<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Form_Repository;
use PHPUnit\Framework\TestCase;

final class FormNumberingTest extends TestCase
{
    public function testFirstFormWhenNoneExist(): void
    {
        $this->assertSame('FORM1', Form_Repository::next_form_name([]));
    }

    public function testIncrementsFromHighestNumberedForm(): void
    {
        // Spec example: FORM2, FORM4, FORM5 present -> next is FORM6 (highest + 1).
        $this->assertSame('FORM6', Form_Repository::next_form_name(['FORM2', 'FORM4', 'FORM5']));
    }

    public function testSimpleIncrement(): void
    {
        $this->assertSame('FORM2', Form_Repository::next_form_name(['FORM1']));
    }

    public function testNonMatchingNamesAreIgnored(): void
    {
        // Spec example: CONTACT, CAPTURE, LEADS present -> next is FORM1.
        $this->assertSame('FORM1', Form_Repository::next_form_name(['CONTACT', 'CAPTURE', 'LEADS']));
    }

    public function testMixOfMatchingAndNonMatching(): void
    {
        $this->assertSame('FORM2', Form_Repository::next_form_name(['FORM1', 'CONTACT', 'Leads']));
    }

    public function testNumericNotLexicalMaximum(): void
    {
        $this->assertSame('FORM11', Form_Repository::next_form_name(['FORM2', 'FORM10']));
    }

    public function testTrailingCharactersDoNotMatch(): void
    {
        $this->assertSame('FORM1', Form_Repository::next_form_name(['FORM1A', 'FORMX', 'FORM']));
    }
}
