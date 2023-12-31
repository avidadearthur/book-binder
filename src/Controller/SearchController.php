<?php

namespace App\Controller;

use App\Entity\BookReviews;
use App\Entity\MeetupList;
use App\Entity\MeetupRequests;
use App\Entity\Book;
use App\Entity\UserPersonalInfo;
use App\Message\AddBookToDatabase;
use DateTime;
use Google_Exception;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Api\GoogleBooksApiClient;
use App\Entity\MeetupRequestList;
use App\Form\MeetupRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/*
 * This controller meant for the development of the
 * GoogleBooksApiClient, it shows examples on how to
 * use the class.
 */

class SearchController extends AbstractController
{
    #[Route('/book-search/{query}', name: 'book-search')]
    public function index($query): Response
    {
        // Access control
        if ($this->getUser() === null) {
            return $this->redirectToRoute('app_login');
        }

        $ApiClient = new GoogleBooksApiClient();

        $results = $ApiClient->searchBooksByTitle(urldecode($query), 40);

        // Pass the results array to the Twig template.
        return $this->render('book_binder/book_search.html.twig', [
            'controller_name' => 'BookBinderController',
            'results' => $results,
            'query' => $query
        ]);
    }

    #[Route('/book-search/ai/{query}', name: 'book-search/ai')]
    public function bookSearchAI($query): JsonResponse
    {
        $ApiClient = new GoogleBooksApiClient();

        // Result is a size 1 array of Google\Service\Books\Volume
        $result = $ApiClient->searchBooksByTitle($query, 1);

        // Check if result is empty
        if (count($result) == 0) {
            return new JsonResponse(null);
        }

        $thumbnail = $result[0]->getVolumeInfo()->getImageLinks()->getThumbnail();
        $id = $result[0]->getId();

        $data = [
            'thumbnail' => $thumbnail,
            'id' => $id,
        ];

        return new JsonResponse($data);
    }

    #[Route('/book-search/openAI/{prompt}', name: 'book-search/openAI')]
    public function bookSearchOpenAI($prompt): JsonResponse
    {
        // Get API key from env (Not working anymore in master???)
        // $apiKey = getenv('GOOGLE_BOOKS_API_KEY');
        $apiKey = "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";
        $model = 'gpt-3.5-turbo';

        $messages = [
            ['role' => 'system', 'content' => 'You are a book assistant, recommend a single book to the user based on their input.'],
            ['role' => 'user', 'content' => "Only respond with the title of the book, no author, no punctuation, recommend me a book about: " . $prompt],
        ];

        $requestBody = [
            'model' => $model,
            'messages' => $messages,
        ];

        $client = OpenAI::client($apiKey);
        $responseData = $client->chat()->create($requestBody);

        $generatedText = $responseData['choices'][0]['message']['content'];
        $data = [
            'text' => $generatedText,
        ];

        return new JsonResponse($data);
    }

    /**
     * @throws Google_Exception
     */
    #[Route('/book-page/{id}', name: 'book-page')]
    public function clickBook($id, Request $request, EntityManagerInterface $entityManager, MessageBusInterface $messageBus): Response
    {
        // Access control
        if ($this->getUser() === null) {
            return $this->redirectToRoute('app_login');
        }


        // ============= API stuff =============
        // Check if book in cache
        $book = $entityManager->getRepository(Book::class)->findOneBy(['google_books_id' => $id]);
        if ($book === null) { // If no book in cache, add it
            $ApiClient = new GoogleBooksApiClient();
            $bookData = $ApiClient->getBookById($id);

            $newBook = new Book();
            $newBook->setGoogleBooksId($bookData['id']);

            if (isset($bookData['volumeInfo']['title'])) {
                $newBook->setTitle($bookData['volumeInfo']['title']);
            } else {
                $newBook->setTitle("");
            }

            if (isset($bookData['volumeInfo']['description'])) {
                Continuation:
                $newBook->setDescription($bookData['volumeInfo']['description']);
            } else {
                $newBook->setDescription("");
            }

            if (isset($bookData['volumeInfo']['imageLinks']['thumbnail'])) {
                $newBook->setThumbnail($bookData['volumeInfo']['imageLinks']['thumbnail']);
            } else {
                $newBook->setThumbnail("");
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
                $newBook->setAuthor("");
            }

            if (isset($bookData['volumeInfo']['pageCount'])) {
                $newBook->setPages($bookData['volumeInfo']['pageCount']);
            } else {
                $newBook->setPages(0);
            }

            if (isset($bookData['volumeInfo']['publishedDate'])) {
                $newBook->setPublishedDate(new DateTime($bookData['volumeInfo']['publishedDate']));
            } else {
                $newBook->setPublishedDate(new DateTime());
            }

            if (isset($bookData['volumeInfo']['categories'])) {
                $newBook->setCategory($bookData['volumeInfo']['categories'][0]);
            } else {
                $newBook->setCategory("");
            }

            // Dispatch a new AddBookToDatabase message
            $messageBus->dispatch(new AddBookToDatabase($newBook)); // why did you use a message bus here?

            $book = $newBook;
        }

        // Derive the book's meetup requests
        $user = $this->getUser();
        $userId = $user->getId();

        //$meetupRequests = $entityManager->getRepository(MeetupRequests::class)->findBy(['book_ID' => $id], ['datetime' => 'DESC'], 10);
        $meetupRequests = $entityManager->createQueryBuilder()
            ->select('mr')
            ->from('App\Entity\MeetupRequests', 'mr')
            ->leftJoin('App\Entity\MeetupList', 'ml', 'WITH', 'mr.meetup_ID = ml.meetup_ID')
            ->leftJoin('App\Entity\MeetupRequestList', 'mrl', 'WITH', 'mr.meetup_ID = mrl.meetup_ID')
            ->where('mr.host_user != :userId AND NOT EXISTS (
                SELECT 1 FROM App\Entity\MeetupList subml
                WHERE subml.meetup_ID = mr.meetup_ID AND subml.user_ID = :userId
            )')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\MeetupRequestList submrl
                WHERE submrl.meetup_ID = mr.meetup_ID AND submrl.user_ID = :userId
            )')
            ->andWhere('mr.book_ID = :bookId')
            ->andWhere('mr.datetime >= :currentDate')
            ->setParameter('userId', $userId)
            ->setParameter('bookId', $id)
            ->setParameter('currentDate', date("Y-m-d h:i:sa"))
            ->orderBy('mr.datetime', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        $filteredMeetupRequests = [];

        foreach ($meetupRequests as $meetupRequest) {
            // Check if the maximum number of participants has been reached
            $participantsCount = $entityManager->getRepository(MeetupList::class)->count(['meetup_ID' => $meetupRequest]);
            $maxParticipants = $meetupRequest->getMaxNumber();

            if ($participantsCount < $maxParticipants - 1) {
                $filteredMeetupRequests[] = $meetupRequest;
            }
        }

        // Add to reading list
        $userReadingList = $user->getUserReadingList();

        // check if book is in one of the user's reading lists
        $wantToRead = $userReadingList->getWantToRead();
        $currentlyReading = $userReadingList->getCurrentlyReading();
        $haveRead = $userReadingList->getHaveRead();

        $bookId = $book->getId();

        $is_in_want_to_read = in_array($bookId, $wantToRead);
        $is_in_currently_reading = in_array($bookId, $currentlyReading);
        $is_in_have_read = in_array($bookId, $haveRead);

        // Host meetup request form
        $meetupRequest = new MeetupRequests();
        // Create the form
        $form = $this->createForm(MeetupRequestFormType::class, $meetupRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $meetupRequest->setHostUser($user);
            $meetupRequest->setBookID($book->getGoogleBooksId());

            $entityManager->persist($meetupRequest);
            $entityManager->flush();

            // Redirect to a success page or do other actions
            return $this->redirectToRoute('book-page', ['id' => $id]);
        }
        // ======== BOOK REVIEWS ========= //

        // Check if the book has any reviews by the current user
        $existingReview = $entityManager->getRepository(BookReviews::class)->findOneBy([
            'user_id' => $user->getId(),
            'book_id' => $book->getGoogleBooksId(),
        ]);

        // if $existingReview is not null then the user has already reviewed the book
        $hasReviewed = $existingReview !== null;

        $reviews = array_slice($entityManager->getRepository(BookReviews::class)->findBy(['book_id' => $id], ['created_at' => 'DESC']), 0, 7);
        $reviewData = [];
        foreach ($reviews as $review) {
            $UserPersonalInfo = $entityManager->getRepository(UserPersonalInfo::class)->findOneBy(['user' => $review->getUserId()]);
            // Put review and username in a 2D array reviewData
            $reviewData[] = [
                'review' => $review,
                'username' => $UserPersonalInfo->getNickname()
            ];
        }

        return $this->render('book_binder/book_page.html.twig', [
            'book' => $book,
            'meetupRequests' => $filteredMeetupRequests,
            'is_in_want_to_read' => $is_in_want_to_read,
            'is_in_currently_reading' => $is_in_currently_reading,
            'is_in_have_read' => $is_in_have_read,
            'form' => $form->createView(),
            'reviewData' => $reviewData,
            'hasReviewed' => $hasReviewed,
            'review' => $existingReview,
        ]);
    }

    //"/book/{bookId}/add-review/{userId}", name="add_review", methods={"POST"})
    #[Route('/add-review/{bookId}', name: 'add_review')]
    public function addReview(Request $request, $bookId, EntityManagerInterface $entityManager): RedirectResponse
    {
        $comment = $request->request->get('comment');
        $rating = $request->request->get('rating');

        // Remove later maybe when bookTitle gets removed from book_reviews?
        $book = $entityManager->getRepository(Book::class)->findOneBy(['google_books_id' => $bookId]);

        $review = new BookReviews();
        $review->setBookID($bookId);
        $user = $this->getUser();
        $review->setUserId($user);
        $review->setReview($comment);
        $review->setCreatedAt(new DateTime());
        $review->setBookTitle($book->getTitle());
        $review->setRating($rating);
        $review->setTags("Hi");

        $entityManager->persist($review);
        $entityManager->flush();

        return $this->redirectToRoute('book-page', ['id' => $bookId]);
    }

    #[Route('/update-review/{bookId}', name: 'update_review')]
    public function updateReview(Request $request, $bookId, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->getUser();
        $existingReview = $entityManager->getRepository(BookReviews::class)->findOneBy([
            'user_id' => $user->getId(),
            'book_id' => $bookId,
        ]);

        $comment = $request->request->get('comment');
        $rating = $request->request->get('rating');

        $existingReview->setReview($comment);
        $existingReview->setRating($rating);

        $entityManager->persist($existingReview);
        $entityManager->flush();

        return $this->redirectToRoute('book-page', ['id' => $bookId]);
    }

    #[Route('/book-page/requests/list/join/{bookId}/{meetupRequestId}', name: 'meetup_requests_list_join_book')]
    public function joinMeetupRequest(String $bookId, int $meetupRequestId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (is_null($user)) {
            return $this->redirectToRoute('app_login');
        }

        // Get the User and MeetupRequest entities based on the provided IDs
        $meetupRequest = $entityManager->getRepository(MeetupRequests::class)->find($meetupRequestId);

        if ($meetupRequest) {
            // Check if the user is already the host
            if ($user != $meetupRequest->getHostUser()) {
                // Check if the maximum number of participants has been reached
                $participantsCount = $entityManager->getRepository(MeetupList::class)->count(['meetup_ID' => $meetupRequest]);
                $maxParticipants = $meetupRequest->getMaxNumber();

                if ($participantsCount < $maxParticipants - 1) {
                    // Check if the user has already joined the meetup request
                    $existingRequest = $entityManager->getRepository(MeetupRequestList::class)->findOneBy(['meetup_ID' => $meetupRequest, 'user_ID' => $user]);

                    if (!$existingRequest) {
                        // Create a new MeetupRequestList entity
                        $meetupRequestList = new MeetupRequestList();
                        $meetupRequestList->setMeetupID($meetupRequest);
                        $meetupRequestList->setUserID($user);

                        // Persist the new entity
                        $entityManager->persist($meetupRequestList);
                        $entityManager->flush();
                    }
                }
            }
        }

        // Redirect or return a response
        return $this->redirectToRoute('book-page', ['id' => $bookId]);
    }

    /**
     * @Route("/book-suggestion/{input}", name="book_suggestion", requirements={"input"=".*"})
     */
    #[Route("/book-suggestion/{input}", name: 'book_suggestion')]
    public function bookSuggestion($input, EntityManagerInterface $entityManager): JsonResponse
    {
        $books = $entityManager->getRepository(Book::class)->createQueryBuilder('b')
            ->where('b.title LIKE :title')
            ->setParameter('title', '%' . $input . '%')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        $suggestions = [];
        foreach ($books as $book) {
            $suggestions[] = [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                // add any other book properties you need
            ];
        }

        return new JsonResponse($suggestions);
    }

    #[Route('/handle-dropdown-selection', name: 'handle-dropdown-selection', methods: ['POST'])]
    public function handleDropdownSelection(Request $request, EntityManagerInterface $entityManager): Response
    {
        $selection = $request->request->get('selection');
        $bookId = (int) $request->request->get('book_id');

        $user = $this->getUser();
        $userReadingList = $user->getUserReadingList();

        $wantToRead = $userReadingList->getWantToRead();
        $currentlyReading = $userReadingList->getCurrentlyReading();
        $haveRead = $userReadingList->getHaveRead();

        $is_in_want_to_read = in_array($bookId, $wantToRead);
        $is_in_currently_reading = in_array($bookId, $currentlyReading);
        $is_in_have_read = in_array($bookId, $haveRead);

        // Perform actions based on the selected value and book ID
        switch ($selection) {
            case 'To Read':
                if ($is_in_want_to_read) {
                    // do nothing
                } else if ($is_in_currently_reading) {
                    // remove from currently reading
                    $currentlyReading = array_diff($currentlyReading, [$bookId]);
                } else if ($is_in_have_read) {
                    // remove from have read
                    $haveRead = array_diff($haveRead, [$bookId]);
                }
                $wantToRead[] = $bookId;
                break;
            case 'Currently Reading':
                if ($is_in_want_to_read) {
                    // remove from want to read
                    $wantToRead = array_diff($wantToRead, [$bookId]);
                } else if ($is_in_currently_reading) {
                    // do nothing
                } else if ($is_in_have_read) {
                    // remove from have read
                    $haveRead = array_diff($haveRead, [$bookId]);
                }
                $currentlyReading[] = $bookId;
                break;
            case 'Have Read':
                if ($is_in_want_to_read) {
                    // remove from want to read
                    $wantToRead = array_diff($wantToRead, [$bookId]);
                } else if ($is_in_currently_reading) {
                    // remove from currently reading
                    $currentlyReading = array_diff($currentlyReading, [$bookId]);
                } else if ($is_in_have_read) {
                    // do nothing
                }
                $haveRead[] = $bookId;
                break;
            default:
                // Handle the case where no or an invalid selection is made
                break;
        }

        // Persist the changes to the database
        $userReadingList->setWantToRead($wantToRead);
        $userReadingList->setCurrentlyReading($currentlyReading);
        $userReadingList->setHaveRead($haveRead);

        $entityManager->persist($userReadingList);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Book added to reading list']); // not sure if that's needed
    }
}
