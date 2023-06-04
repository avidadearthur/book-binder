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

namespace App\Message;

use App\Entity\Book;

class AddBookToDatabase
{
    private Book $bookData;

    public function __construct(Book $bookData)
    {
        $this->bookData = $bookData;
    }

    public function getBookData(): Book
    {
        return $this->bookData;
    }
}
