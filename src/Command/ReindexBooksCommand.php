<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BookFileRepository;
use App\Repository\BookRepository;
use App\Service\BookCoverGenerator;
use App\Service\FileStorage;
use App\Service\TextExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:books:reindex', description: 'Переиндексировать книги: обложки и текст для поиска')]
final class ReindexBooksCommand extends Command
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookFileRepository $bookFiles,
        private readonly EntityManagerInterface $em,
        private readonly BookCoverGenerator $coverGenerator,
        private readonly TextExtractor $textExtractor,
        private readonly FileStorage $storage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('covers', null, InputOption::VALUE_NONE, 'Только обложки')
            ->addOption('text', null, InputOption::VALUE_NONE, 'Только текст')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перегенерировать даже там, где уже есть');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $only = $input->getOption('covers') ? 'covers' : ($input->getOption('text') ? 'text' : 'both');
        $force = (bool) $input->getOption('force');

        if ($only !== 'text') {
            $io->section('Обложки');
            $books = $this->books->createQueryBuilder('b')->getQuery()->getResult();
            $count = 0;
            foreach ($books as $book) {
                if ($book->hasCover() && !$force) {
                    continue;
                }
                $pdfFile = null;
                foreach ($book->getFiles() as $f) {
                    if ($f->getFormat()->value === 'pdf') {
                        $pdfFile = $f;
                        break;
                    }
                }
                if ($pdfFile === null) {
                    continue;
                }
                $path = $this->coverGenerator->generateFromPdf(
                    $this->storage->absolutePath($pdfFile->getStoredPath()),
                    $book->getId(),
                );
                if ($path !== null) {
                    $book->setCoverPath($path);
                    $count++;
                    $io->writeln("  обложка: {$book->getTitle()}");
                }
            }
            $this->em->flush();
            $io->success("Сгенерировано обложек: {$count}");
        }

        if ($only !== 'covers') {
            $io->section('Экстракция текста');
            $files = $this->bookFiles->findAll();
            $count = 0;
            foreach ($files as $file) {
                if ($file->getTextStatus() === 'processed' && !$force) {
                    continue;
                }
                try {
                    $text = $this->textExtractor->extract($file->getFormat(), $file->getStoredPath());
                    if ($text !== null && $text !== '') {
                        $file->setExtractedText($text);
                        $file->setTextStatus('processed');
                        $count++;
                        $io->writeln("  текст: {$file->getOriginalName()}");
                    } else {
                        $file->setTextStatus('skipped');
                    }
                } catch (\Throwable $e) {
                    $file->setTextStatus('failed');
                    $io->writeln("  <error>ошибка: {$file->getOriginalName()}: {$e->getMessage()}</error>");
                }
                $this->em->flush();
            }
            $io->success("Проиндексировано файлов: {$count}");
        }

        return Command::SUCCESS;
    }
}
