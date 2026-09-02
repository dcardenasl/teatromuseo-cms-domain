<?php

declare(strict_types=1);

namespace Tests\Unit\Validations;

use App\Validations\Rules\CustomRules;
use CodeIgniter\Test\CIUnitTestCase;

class CustomRulesTest extends CIUnitTestCase
{
    private CustomRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new CustomRules();
    }

    public function testBooleanLikeAcceptsTrueBoolean(): void
    {
        $error = null;
        $this->assertTrue($this->rules->boolean_like(true, $error));
        $this->assertNull($error);
    }

    public function testBooleanLikeAcceptsFalseBoolean(): void
    {
        $error = null;
        $this->assertTrue($this->rules->boolean_like(false, $error));
        $this->assertNull($error);
    }

    public function testBooleanLikeAcceptsZeroAndOneInts(): void
    {
        $this->assertTrue($this->rules->boolean_like(0));
        $this->assertTrue($this->rules->boolean_like(1));
    }

    public function testBooleanLikeRejectsOtherIntegers(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like(2, $error));
        $this->assertNotEmpty($error);
    }

    public function testBooleanLikeAcceptsStringDigits(): void
    {
        $this->assertTrue($this->rules->boolean_like('0'));
        $this->assertTrue($this->rules->boolean_like('1'));
    }

    public function testBooleanLikeAcceptsTruthyAndFalsyStrings(): void
    {
        foreach (['true', 'false', 'yes', 'no', 'on', 'off'] as $value) {
            $this->assertTrue($this->rules->boolean_like($value), sprintf('Expected "%s" to be valid', $value));
        }
    }

    public function testBooleanLikeIsCaseInsensitive(): void
    {
        foreach (['TRUE', 'False', 'YES', 'No', 'ON', 'Off'] as $value) {
            $this->assertTrue($this->rules->boolean_like($value), sprintf('Expected "%s" to be valid', $value));
        }
    }

    public function testBooleanLikeRejectsArbitraryString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like('banana', $error));
        $this->assertNotEmpty($error);
    }

    public function testBooleanLikeRejectsNull(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like(null, $error));
        $this->assertNotEmpty($error);
    }

    public function testBooleanLikeRejectsArray(): void
    {
        $error = null;
        $this->assertFalse($this->rules->boolean_like(['true'], $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordAcceptsValidPassword(): void
    {
        $error = null;
        $this->assertTrue($this->rules->strong_password('Str0ng!Pass', $error));
        $this->assertNull($error);
    }

    public function testStrongPasswordRejectsNullAndEmpty(): void
    {
        $this->assertFalse($this->rules->strong_password(null));
        $this->assertFalse($this->rules->strong_password(''));
    }

    public function testStrongPasswordRejectsTooShort(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Sh0rt!', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsTooLong(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password(str_repeat('aA1!', 40), $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingLowercase(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('STR0NG!PASS', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingUppercase(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('str0ng!pass', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingDigit(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Strong!Pass', $error));
        $this->assertNotEmpty($error);
    }

    public function testStrongPasswordRejectsMissingSpecialCharacter(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Str0ngPass', $error));
        $this->assertNotEmpty($error);
    }

    public function testValidEmailIdnAcceptsPlainAsciiEmail(): void
    {
        $this->assertTrue($this->rules->valid_email_idn('visitor@example.com'));
    }

    public function testValidEmailIdnAcceptsInternationalDomain(): void
    {
        $this->assertTrue($this->rules->valid_email_idn('visitor@münchen.de'));
    }

    public function testValidEmailIdnRejectsNullAndEmpty(): void
    {
        $this->assertFalse($this->rules->valid_email_idn(null));
        $this->assertFalse($this->rules->valid_email_idn(''));
    }

    public function testValidEmailIdnRejectsMalformedAddress(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn('not-an-email', $error));
        $this->assertNotEmpty($error);
    }

    public function testValidUuidAcceptsUuidV4(): void
    {
        $this->assertTrue($this->rules->valid_uuid('a1b2c3d4-e5f6-4789-a123-b456c789d012'));
    }

    public function testValidUuidRejectsNullAndEmpty(): void
    {
        $this->assertFalse($this->rules->valid_uuid(null));
        $this->assertFalse($this->rules->valid_uuid(''));
    }

    public function testValidUuidRejectsNonUuidString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_uuid('not-a-uuid', $error));
        $this->assertNotEmpty($error);
    }

    public function testValidTokenAcceptsHexOfExpectedLength(): void
    {
        $this->assertTrue($this->rules->valid_token(str_repeat('a', 64)));
    }

    public function testValidTokenRejectsNullAndEmpty(): void
    {
        $this->assertFalse($this->rules->valid_token(null));
        $this->assertFalse($this->rules->valid_token(''));
    }

    public function testValidTokenRejectsNonHexString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_token(str_repeat('z', 64), '64', [], $error));
        $this->assertNotEmpty($error);
    }

    public function testValidTokenRejectsWrongLength(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_token(str_repeat('a', 10), '64', [], $error));
        $this->assertNotEmpty($error);
    }

    public function testValidTokenRespectsCustomExpectedLength(): void
    {
        $this->assertTrue($this->rules->valid_token(str_repeat('a', 32), '32'));
    }

    public function testIsListAcceptsSequentialArray(): void
    {
        $this->assertTrue($this->rules->is_list(['a', 'b', 'c']));
        $this->assertTrue($this->rules->is_list([]));
    }

    public function testIsListRejectsAssociativeArray(): void
    {
        $error = null;
        $this->assertFalse($this->rules->is_list(['key' => 'value'], $error));
        $this->assertNotEmpty($error);
    }

    public function testIsListRejectsNonArray(): void
    {
        $error = null;
        $this->assertFalse($this->rules->is_list('not-an-array', $error));
        $this->assertNotEmpty($error);
    }

    public function testJsonAcceptsValidJsonString(): void
    {
        $this->assertTrue($this->rules->json('{"key":"value"}'));
    }

    public function testJsonRejectsNonString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->json(['not', 'a', 'string'], $error));
        $this->assertNotEmpty($error);
    }

    public function testJsonRejectsMalformedString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->json('{not valid json', $error));
        $this->assertNotEmpty($error);
    }
}
