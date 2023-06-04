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

namespace App\Controller;

use App\Api\GoogleBooksApiClient;
use App\Entity\Book;
use App\Entity\BookReviews;
use App\Message\AddBookToDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class BookBinderController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(EntityManagerInterface $entityManager, MessageBusInterface $messageBus): Response
    {
        $user = $this->getUser();

        if (null === $user) {
            return $this->redirectToRoute('app_login');
        } else {
            try {
                $genres = $user->getUserReadingInterest()->getGenres();
            } catch (\Throwable $th) {
                return $this->redirectToRoute('reading_interest');
            }
        }

        // ============= API stuff =============
        $ApiClient = new GoogleBooksApiClient();

        // if the user has no genres, add some default ones.
        if (0 === \count($genres)) {
            array_push($genres, 'Fantasy', 'popular', 'classic');
        }

        // Create an empty array to hold the results.
        $results = [];

        // ==================== First query the database to see if we have any books for the user's genres.
        // ==================== If no books were found in the database, query the API to retrieve them.
        // Loop through each genre and retrieve the popular books.
        foreach ($genres as $genre) {
            $books = $entityManager->getRepository(Book::class)->findBy(['category' => $genre], ['id' => 'DESC'], limit: 40);
            $results[$genre] = $books;
            $cachedCount = \count($books);

            if ($cachedCount < 40) {
                $books = $ApiClient->getBooksBySubject($genre, 40 - $cachedCount);

                // Create a new array to store the Book objects
                $bookObjects = [];

                // for each book in the results array, create a new Book object and add it to the database.

                foreach ($books as $bookData) {
                    $existingBook = $entityManager->getRepository(Book::class)->findOneBy(['google_books_id' => $bookData['id']]);

                    if (null === $existingBook) {
                        // Complete the book object with the data from the API
                        $newBook = new Book();
                        $newBook->setGoogleBooksId($bookData['id']);

                        if (isset($bookData['volumeInfo']['title'])) {
                            $newBook->setTitle($bookData['volumeInfo']['title']);
                        } else {
                            $newBook->setTitle('');
                        }

                        if (isset($bookData['volumeInfo']['description'])) {
                            Continuation:
                            $newBook->setDescription($bookData['volumeInfo']['description']);
                        } else {
                            $newBook->setDescription('');
                        }

                        if (isset($bookData['volumeInfo']['imageLinks']['thumbnail'])) {
                            $newBook->setThumbnail($bookData['volumeInfo']['imageLinks']['thumbnail']);
                        } else {
                            $newBook->setThumbnail('');
                        }

                        if (isset($bookData['volumeInfo']['averageRating'])) {
                            $newBook->setRating($bookData['volumeInfo']['averageRating']);
                        } else {
                            $newBook->setRating(0);
                        }

                        if (isset($bookData['volumeInfo']['ratingsCount'])) {
                            $newBook->setReviewCount($bookData['volumeInfo']['ratingsCount']);
                        } else {
                            $newBook->setReviewCount(0);
                        }

                        if (isset($bookData['volumeInfo']['authors'][0])) {
                            $newBook->setAuthor($bookData['volumeInfo']['authors'][0]);
                        } else {
                            $newBook->setAuthor('');
                        }

                        if (isset($bookData['volumeInfo']['pageCount'])) {
                            $newBook->setPages($bookData['volumeInfo']['pageCount']);
                        } else {
                            $newBook->setPages(0);
                        }

                        if (isset($bookData['volumeInfo']['publishedDate'])) {
                            $newBook->setPublishedDate(new \DateTime($bookData['volumeInfo']['publishedDate']));
                        } else {
                            $newBook->setPublishedDate(new \DateTime());
                        }

                        $newBook->setCategory($genre);

                        // Dispatch a new AddBookToDatabase message
                        $messageBus->dispatch(new AddBookToDatabase($newBook));

                        // Check if any non-nullable fields are missing
                        if (null !== $newBook->getTitle() && null !== $newBook->getAuthor()) {
                            // Add the Book object to the array
                            $bookObjects[] = $newBook;
                        }
                    }
                }

                // Assign the Book objects to the $results array
                if (null === $results[$genre]) {
                    $results[$genre] = $bookObjects;
                } else {
                    $results[$genre] = array_merge($results[$genre], $bookObjects);
                }
            }
        }
        // ============= Reviews

        $reviews = $entityManager->getRepository(BookReviews::class)->findLatest(10);

        // =============

        return $this->render('book_binder/index.html.twig', [
            'controller_name' => 'BookBinderController',
            'results' => $results,
            'reviews' => $reviews,
        ]);
    }
}
