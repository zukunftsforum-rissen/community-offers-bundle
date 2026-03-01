<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\Form;
use Contao\FrontendUser;
use Contao\Widget;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use ZukunftsforumRissen\CommunityOffersBundle\EventListener\LoadFormFieldListener;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;

class LoadFormFieldListenerTest extends TestCase
{
    /**
     * Verifies unrelated forms are left untouched.
     */
    public function testInvokeReturnsOriginalWidgetForOtherFormIds(): void
    {
        $listener = new LoadFormFieldListener($this->createStub(Security::class), $this->createStub(AccessService::class));
        $widget = $this->createWidget('fullName');

        $result = $listener->__invoke($widget, 'auto_contact', [], $this->createStub(Form::class));

        $this->assertSame($widget, $result);
    }

    /**
     * Verifies additional access form is unchanged when no frontend user is authenticated.
     */
    public function testInvokeReturnsOriginalWidgetWhenUserIsNotFrontendUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $listener = new LoadFormFieldListener($security, $this->createStub(AccessService::class));
        $widget = $this->createWidget('fullName');

        $result = $listener->__invoke($widget, 'auto_additional_access_request', [], $this->createStub(Form::class));

        $this->assertSame($widget, $result);
    }

    /**
     * Verifies fullName field is prefilled and locked for authenticated frontend users.
     */
    public function testInvokePrefillsAndLocksFullNameField(): void
    {
        $user = $this->createFrontendUser(11, 'Ada', 'Lovelace');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $listener = new LoadFormFieldListener($security, $this->createStub(AccessService::class));
        $widget = $this->createWidget('fullName');

        $result = $listener->__invoke($widget, 'auto_additional_access_request', [], $this->createStub(Form::class));

        $this->assertSame($widget, $result);
        $this->assertSame('Ada Lovelace', (string) $widget->value);
        $this->assertTrue((bool) $widget->readonly);
        $this->assertTrue((bool) $widget->disabled);
    }

    /**
     * Verifies already granted areas are removed from requestedAreas options.
     */
    public function testInvokeFiltersGrantedAreasFromRequestedAreasOptions(): void
    {
        $user = $this->createFrontendUser(12, 'Max', 'Muster');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->expects($this->once())
            ->method('getGrantedAreasForMemberId')
            ->with(12)
            ->willReturn(['depot'])
        ;

        $listener = new LoadFormFieldListener($security, $accessService);
        $widget = $this->createWidget('requestedAreas');
        $widget->options = [
            ['value' => 'depot', 'label' => 'Depot'],
            ['value' => 'sharing', 'label' => 'Sharing'],
            ['label' => 'no-value-kept'],
        ];

        $listener->__invoke($widget, 'auto_additional_access_request', [], $this->createStub(Form::class));

        $this->assertSame([
            ['value' => 'sharing', 'label' => 'Sharing'],
            ['label' => 'no-value-kept'],
        ], $widget->options);
    }

    private function createWidget(string $name): Widget
    {
        $widget = $this->getMockForAbstractClass(Widget::class, [], '', false, false, true, []);
        $widget->name = $name;

        return $widget;
    }

    private function createFrontendUser(int $id, string $firstname, string $lastname): FrontendUser
    {
        $user = $this->getMockBuilder(FrontendUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock()
        ;

        $user->id = $id;
        $user->firstname = $firstname;
        $user->lastname = $lastname;

        return $user;
    }
}
