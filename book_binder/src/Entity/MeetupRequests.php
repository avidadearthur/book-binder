<?php

namespace App\Entity;

use App\Repository\MeetupRequestsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MeetupRequestsRepository::class)]
class MeetupRequests
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $meetup_ID = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'library_ID', referencedColumnName: 'library_ID', nullable: false)]
    private ?Library $library_ID = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $host_user = null;

    #[ORM\Column]
    private ?int $book_ID = null;

    #[ORM\Column]
    private ?int $max_number = null;

    public function getMeetupID(): ?int
    {
        return $this->meetup_ID;
    }

    public function getLibraryID(): ?Library
    {
        return $this->library_ID;
    }

    public function setLibraryID(?Library $library_ID): self
    {
        $this->library_ID = $library_ID;

        return $this;
    }

    public function getHostUser(): ?User
    {
        return $this->host_user;
    }

    public function setHostUser(?User $host_user): self
    {
        $this->host_user = $host_user;

        return $this;
    }

    public function getBookID(): ?int
    {
        return $this->book_ID;
    }

    public function setBookID(int $book_ID): self
    {
        $this->book_ID = $book_ID;

        return $this;
    }

    public function getMaxNumber(): ?int
    {
        return $this->max_number;
    }

    public function setMaxNumber(int $max_number): self
    {
        $this->max_number = $max_number;

        return $this;
    }
}