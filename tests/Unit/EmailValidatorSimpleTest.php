<?php

declare(strict_types=1);

use App\Domain\Validate\EmailValidatorSimple;
use PHPUnit\Framework\TestCase;

final class EmailValidatorSimpleTest extends TestCase
{
    public function testOk(): void
    {
        $ok_data = [
            'a@b',
            'a@b.c',
        ];

        $validator = new EmailValidatorSimple();

        foreach ($ok_data as $email) {
            $r = $validator->validate($email);
            $this->assertNotSame(false, $r, "Falsed: {$email}");
            // $this->assertSame($email, $r);
        }
    }

    public function testNg(): void
    {
        $ng_data = [
            '',
            'a',
            'a@',
            '@b',
        ];

        $validator = new EmailValidatorSimple();

        foreach ($ng_data as $email) {
            $r = $validator->validate($email);
            $this->assertSame(false, $r, "Falsed: {$email}");
            // $this->assertFalse($r);
        }
    }
}
