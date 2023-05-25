<?php

namespace App\DataFixtures;

use App\Entity\BookReviews;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class BookReviewFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $bookReview = new BookReviews();
            $bookReview->setBookID($faker->numberBetween(1, 1000));
            $bookReview->setRating($faker->numberBetween(1, 5));
            $bookReview->setTags($faker->words(1, true));
            $bookReview->setReview('#' . $bookReview->getTags() . '# ' . $faker->paragraphs(3, true));
            $bookReview->setCreatedAt($faker->dateTimeBetween('-1 days', 'now'));
            $user = $this->getReference(UserFixtures::USER_REFERENCE);
            $bookReview->setUserID($user);

            $manager->persist($bookReview);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}