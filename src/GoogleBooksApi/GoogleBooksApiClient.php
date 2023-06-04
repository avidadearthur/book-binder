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

namespace App\Api;

use Google\Client as Google_Client;
use Google_Service_Books;
use GuzzleHttp\Client;

/**
 * Class used for interacting with the google books API.
 *
 * Has methods for getting an array of books based on a text query,
 * simply the newest books of a certain subject
 * and the most popular ("relevant") books of a certain subject
 */
class GoogleBooksApiClient
{
    // Get API key from .env
    private string $API_KEY;

    public function __construct()
    {
        $this->API_KEY = getenv('GOOGLE_BOOKS_API_KEY');
    }

    /**
     * Retrieves an array of books from the Google Books API that match a specified title.
     *
     * @param string $title      the title to search for
     * @param int    $maxResults The maximum number of results to return. Defaults to 5.
     * @param int    $startIndex The index of the first result to return (used for pagination). Defaults to 0.
     *
     * @return array an array of book objects that match the specified title
     */
    public function searchBooksByTitle(string $title, int $maxResults = 5, int $startIndex = 0): array
    {
        // Get Google Books API service
        $service = $this->getBooksService();

        try {
            // Set the parameters for the API request to search for books by title.
            $query = 'intitle:'.urlencode($title);

            $optParams = [
                'maxResults' => $maxResults,
                'startIndex' => $startIndex,
            ];

            // Make the API request to search for books by title.
            $results = $service->volumes->listVolumes($query, $optParams);

            // Return the array of book objects.
            return $results->getItems();
        } catch (\Google_Exception $e) {
            // If an error occurs, log the error and return an empty array.
            error_log('Error searching for books by title: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Retrieves an array of books from the Google Books API that match a specified subject.
     *
     * @param string $subject    the subject to search for
     * @param int    $maxResults The maximum number of results to return. Defaults to 3.
     * @param int    $startIndex The index of the first result to return (used for pagination). Defaults to 0.
     *
     * @return array an array of book objects that match the specified subject
     */
    public function getBooksBySubject(string $subject, int $maxResults = 40, int $startIndex = 0): array
    {
        // Get Google Books API service
        $service = $this->getBooksService();

        try {
            // Set the parameters for the API request to search for books by subject.
            $query = 'subject:'.urlencode($subject);

            $optParams = [
                'maxResults' => $maxResults,
                'startIndex' => $startIndex,
            ];

            // Make the API request to search for books by subject.
            $results = $service->volumes->listVolumes($query, $optParams);

            // Return the array of book objects.
            return $results->getItems();
        } catch (\Google_Exception $e) {
            // If an error occurs, log the error and return an empty array.
            error_log('Error searching for books by subject: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Retrieves a book from the Google Books API by its ID.
     *
     * @param string $bookId the ID of the book to retrieve
     *
     * @return \Google_Service_Books_Volume|null the book object, or null if not found
     *
     * @throws \Google_Exception if an error occurs while making the API request
     */
    public function getBookById(string $bookId): ?\Google_Service_Books_Volume
    {
        // Get google books API service
        $service = $this->getBooksService();

        try {
            // Make the API request to retrieve the book by ID.
            $book = $service->volumes->get($bookId);

            // Return the book object if found.
            return $book;
        } catch (\Google_Exception $e) {
            // If the book is not found, return null.
            if (404 === $e->getCode()) {
                return null;
            }

            // Otherwise, rethrow the exception.
            throw $e;
        }
    }

    // Helper function to initialize the Google_Client and Google_Service_Books objects.
    private function getBooksService(): \Google_Service_Books
    {
        $client = new Google_Client();
        $client->setDeveloperKey($this->API_KEY);
        $client->setApplicationName('My App');
        $client->setScopes(['https://www.googleapis.com/auth/books']);
        $client->setHttpClient(new Client([
            'verify' => false,
        ]));

        return new \Google_Service_Books($client);
    }
}
