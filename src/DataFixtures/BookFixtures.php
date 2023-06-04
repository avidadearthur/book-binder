<?php

/*
 *             ████████   ████████                           █████      ████████  ████
 *            ███░░░░███ ███░░░░███                         ░░███      ███░░░░███░░███
 *   ██████  ░░░    ░███░░░    ░███ █████ ███ █████  ██████  ░███████ ░░░    ░███ ░███
 *  ░░░░░███    ███████    ███████ ░░███ ░███░░███  ███░░███ ░███░░███   ██████░  ░███
 *   ███████   ███░░░░    ███░░░░   ░███ ░███ ░███ ░███████  ░███ ░███  ░░░░░░███ ░███
 *  ███░░███  ███      █ ███      █ ░░███████████  ░███░░░   ░███ ░███ ███   ░███ ░███
 * ░░████████░██████████░██████████  ░░████░████   ░░██████  ████████ ░░████████  █████
 *  ░░░░░░░░ ░░░░░░░░░░ ░░░░░░░░░░    ░░░░ ░░░░     ░░░░░░  ░░░░░░░░   ░░░░░░░░  ░░░░░
 *
 *  This file is part of the a22web31 - web technology project.
 *
 */

namespace App\DataFixtures;

use App\Entity\Book;
use App\Enum\GenreEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class BookFixtures extends Fixture implements DependentFixtureInterface
{
    private $bookIds = [
        'bgpfDwAAQBAJ',
        'E5a17DrqHeUC',
        'CjAs4stLXhAC',
        'SXUOAAAAQAAJ',
        'Pf1KAAAAMAAJ',
        'soSsLATmZnkC',
        'BD9nPgAACAAJ',
        'ydXRAHJ5SCQC',
        'dzxVAAAAMAAJ',
        '2LM3AAAAMAAJ',
    ];

    public const BOOK_REFERENCE = 'book_ref';

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        // Get all genre choices
        $genreChoices = GenreEnum::getChoices();

        for ($i = 1; $i <= 10; ++$i) {
            $book = new Book();
            $book->setGoogleBooksId($this->bookIds[$i - 1]);
            $book->setTitle($faker->sentence(5));
            $book->setDescription($faker->paragraph(1));
            $book->setThumbnail($faker->imageUrl());
            $book->setRating($faker->numberBetween(1, 5));
            $book->setReviewCount($faker->numberBetween(10, 100));
            $book->setAuthor($faker->name());
            $book->setPages($faker->numberBetween(100, 500));
            $book->setPublishedDate($faker->dateTimeThisDecade());
            $book->setCategory($faker->randomElement($genreChoices));

            $manager->persist($book);

            // Create a unique reference for each book
            $this->addReference(self::BOOK_REFERENCE.$i, $book);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ResetAutoincrementFixture::class,
        ];
    }
}
