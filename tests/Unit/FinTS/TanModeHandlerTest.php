<?php

declare(strict_types=1);

namespace Tests\Unit\FinTS;

use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\TanMode;
use Fhp\Model\TanRequest;
use Gared\FireflyImporter\FinTS\TanModeHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

class TanModeHandlerTest extends TestCase
{
    private BaseAction&MockObject $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = $this->createMock(BaseAction::class);
    }

    public function testHandleReturnsEarlyWhenActionDoesNotNeedTan(): void
    {
        $handler = new TanModeHandler();

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::never())->method('getSelectedTanMode');

        $this->action->expects(self::once())->method('needsTan')->willReturn(false);

        $io = $this->createStub(SymfonyStyle::class);

        $handler->handle($finTs, $this->action, $io);
    }

    public function testHandleThrowsWhenNoTanModeWasSelected(): void
    {
        $handler = new TanModeHandler();
        $this->action->expects(self::once())->method('needsTan')->willReturn(true);

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::once())
            ->method('getSelectedTanMode')
            ->willReturn(null);

        $io = $this->createStub(SymfonyStyle::class);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessageIs('No tan mode was selected');

        $handler->handle($finTs, $this->action, $io);
    }

    public function testHandleCoupledTanSubmitsTanFromHiddenPrompt(): void
    {
        $handler = new TanModeHandler();
        $tanRequest = $this->createMock(TanRequest::class);
        $tanRequest->expects(self::exactly(2))->method('getChallenge')->willReturn('Open your banking app');
        $tanRequest->expects(self::exactly(2))->method('getTanMediumName')->willReturn('My Phone');

        $this->action->expects(self::once())->method('needsTan')->willReturn(true);
        $this->action->expects(self::once())->method('getTanRequest')->willReturn($tanRequest);

        $tanMode = $this->createStub(TanMode::class);
        $tanMode->method('isDecoupled')->willReturn(false);

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::once())
            ->method('getSelectedTanMode')
            ->willReturn($tanMode);
        $finTs->expects(self::once())
            ->method('submitTan')
            ->with(self::identicalTo($this->action), '123456');

        $messages = [];
        $io = $this->getMockBuilder(SymfonyStyle::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['info', 'askHidden'])
            ->getMock();
        $io->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(static function (string $message) use (&$messages): void {
                $messages[] = $message;
            });
        $io->expects(self::once())
            ->method('askHidden')
            ->with('Please enter your TAN')
            ->willReturn('123456');

        $handler->handle($finTs, $this->action, $io);

        self::assertSame([
            'Instructions: Open your banking app',
            'Please use this device: My Phone',
        ], $messages);
    }

    public function testHandleCoupledTanThrowsOnEmptyTan(): void
    {
        $handler = new TanModeHandler();
        $tanRequest = $this->createMock(TanRequest::class);
        $tanRequest->expects(self::once())->method('getChallenge')->willReturn(null);
        $tanRequest->expects(self::once())->method('getTanMediumName')->willReturn(null);

        $this->action->expects(self::once())->method('needsTan')->willReturn(true);
        $this->action->expects(self::once())->method('getTanRequest')->willReturn($tanRequest);

        $tanMode = $this->createStub(TanMode::class);
        $tanMode->method('isDecoupled')->willReturn(false);

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::once())
            ->method('getSelectedTanMode')
            ->willReturn($tanMode);
        $finTs->expects(self::never())
            ->method('submitTan');

        $io = $this->getMockBuilder(SymfonyStyle::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['askHidden'])
            ->getMock();
        $io->expects(self::once())
            ->method('askHidden')
            ->with('Please enter your TAN')
            ->willReturn('   ');

        self::expectException(RuntimeException::class);
        self::expectExceptionMessageIs('TAN must not be empty');

        $handler->handle($finTs, $this->action, $io);
    }
}
