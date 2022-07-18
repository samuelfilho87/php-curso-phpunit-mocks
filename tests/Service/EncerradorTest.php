<?php


namespace Service;


use Alura\Leilao\Model\Leilao;
use Alura\Leilao\Dao\Leilao as LeilaoDao;
use Alura\Leilao\Service\Encerrador;
use Alura\Leilao\Service\EnviadorEmail;
use PHPUnit\Framework\TestCase;


class EncerradorTest extends TestCase
{
    private $encerrador;
    /** @var MockObject */
    private $enviadorEmail;
    private $fiat147;
    private $variante;

    protected function setUp(): void
    {
        $this->fiat147 = new Leilao(
            'Fiat 147 0km',
            new \DateTimeImmutable('8 days ago')
        );

        $this->variante = new Leilao(
            'Variante 1972 0km',
            new \DateTimeImmutable('10 days ago')
        );

        $leilaoDao = $this->createMock(LeilaoDao::class);

        $leilaoDao->method('recuperarNaoFinalizados')
            ->willReturn([$this->fiat147, $this->variante]);

        $leilaoDao->method('recuperarFinalizados')
            ->willReturn([$this->fiat147, $this->variante]);

        $leilaoDao->expects($this->exactly(2))
            ->method('atualiza')
            ->withConsecutive(
                [$this->fiat147],
                [$this->variante]
            );

        $this->enviadorEmail = $this->createMock(EnviadorEmail::class);

        $this->encerrador = new Encerrador($leilaoDao, $this->enviadorEmail);
    }

    public function testLeiloesComMaisDeUmaSemanaDevemSerEncerrados()
    {
        $this->encerrador->encerra();

        $leiloes = [$this->fiat147, $this->variante];

        self::assertCount(2, $leiloes);
        self::assertTrue($leiloes[0]->estaFinalizado());
        self::assertTrue($leiloes[1]->estaFinalizado());
        self::assertEquals(
            'Fiat 147 0km',
            $leiloes[0]->recuperarDescricao()
        );
        self::assertEquals(
            'Variante 1972 0km',
            $leiloes[1]->recuperarDescricao()
        );
    }

    public function testDeveContinuarOProcessamentoAoEncontrarErroAoEnviarEmail()
    {
        $e = new \DomainException('Erro ao enviar e-mail');

        $this->enviadorEmail->expects($this->exactly(2))
            ->method('notificarTerminoLeilao')
            ->willThrowException($e);

        $this->encerrador->encerra();
    }

    public function testSoDeveEnviarLeilaoPorEmailAposFinalizado()
    {
        $this->enviadorEmail->expects($this->exactly(2))
            ->method('notificarTerminoLeilao')
            ->willReturnCallback(function (Leilao $leilao) {
                static::assertTrue($leilao->estaFinalizado());
            });

        $this->encerrador->encerra();
    }
}