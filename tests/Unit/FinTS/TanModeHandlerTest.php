<?php

declare(strict_types=1);

namespace Tests\Unit\FinTS;

use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\TanMode;
use Fhp\Model\TanRequest;
use Fhp\Protocol\BPD;
use Fhp\Protocol\UPD;
use Gared\FireflyImporter\FinTS\TanModeHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

class TanModeHandlerTest extends TestCase
{
    public function testHandleReturnsEarlyWhenActionDoesNotNeedTan(): void
    {
        $handler = new TanModeHandler();
        $action = new DummyAction();

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::never())->method('getSelectedTanMode');

        $io = $this->createStub(SymfonyStyle::class);

        $handler->handle($finTs, $action, $io);

        self::assertFalse($action->needsTan());
    }

    public function testHandleThrowsWhenNoTanModeWasSelected(): void
    {
        $handler = new TanModeHandler();
        $action = new DummyAction();
        $action->setTanRequest(new DummyTanRequest('pid-1', null, null));

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::once())
            ->method('getSelectedTanMode')
            ->willReturn(null);

        $io = $this->createStub(SymfonyStyle::class);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessageIs('No tan mode was selected');

        $handler->handle($finTs, $action, $io);
    }

    public function testHandleCoupledTanSubmitsTanFromHiddenPrompt(): void
    {
        $handler = new TanModeHandler();
        $action = new DummyAction();
        $action->setTanRequest(new DummyTanRequest('pid-2', 'Open your banking app', 'My Phone'));

        $tanMode = $this->createStub(TanMode::class);
        $tanMode->method('isDecoupled')->willReturn(false);

        $finTs = $this->createMock(FinTs::class);
        $finTs->expects(self::once())
            ->method('getSelectedTanMode')
            ->willReturn($tanMode);
        $finTs->expects(self::once())
            ->method('submitTan')
            ->with(self::identicalTo($action), '123456');

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

        $handler->handle($finTs, $action, $io);

        self::assertSame([
            'Instructions: Open your banking app',
            'Please use this device: My Phone',
        ], $messages);
    }

    public function testHandleCoupledTanThrowsOnEmptyTan(): void
    {
        $handler = new TanModeHandler();
        $action = new DummyAction();
        $action->setTanRequest(new DummyTanRequest('pid-3', null, null));

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

        $handler->handle($finTs, $action, $io);
    }
}

final class DummyAction extends BaseAction
{
    protected function createRequest(BPD $bpd, ?UPD $upd)
    {
        return [];
    }
}

final class DummyTanRequest implements TanRequest
{
    public function __construct(
        private readonly string $processId,
        private readonly ?string $challenge,
        private readonly ?string $tanMediumName,
    ) {
    }

    public function getProcessId(): string
    {
        return $this->processId;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }

    public function getTanMediumName(): ?string
    {
        return $this->tanMediumName;
    }

    public function getChallengeHhdUc(): ?\Fhp\Syntax\Bin
    {
        return null;
    }
}


