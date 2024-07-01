<?php

namespace App\Entity;

use App\Repository\CreatorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreatorRepository::class)]
class Creator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'answered_by')]
    private Collection $questions;

    #[ORM\Column(options: ['default' => 3])]
    private ?int $lives = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $img = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'voted_by')]
    private ?self $voted_for = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'voted_for')]
    private Collection $voted_by;

    #[ORM\Column(length: 255)]
    private ?string $auth = null;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->voted_by = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setAnsweredBy($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getAnsweredBy() === $this) {
                $question->setAnsweredBy(null);
            }
        }

        return $this;
    }

    public function getLives(): ?int
    {
        return $this->lives;
    }

    public function setLives(int $lives): static
    {
        $this->lives = $lives;

        return $this;
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): static
    {
        $this->img = $img;

        return $this;
    }

    public function getVotedFor(): ?self
    {
        return $this->voted_for;
    }

    public function setVotedFor(?self $voted_for): static
    {
        $this->voted_for = $voted_for;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getVotedBy(): Collection
    {
        return $this->voted_by;
    }

    public function addVotedBy(self $votedBy): static
    {
        if (!$this->voted_by->contains($votedBy)) {
            $this->voted_by->add($votedBy);
            $votedBy->setVotedFor($this);
        }

        return $this;
    }

    public function removeVotedBy(self $votedBy): static
    {
        if ($this->voted_by->removeElement($votedBy)) {
            // set the owning side to null (unless already changed)
            if ($votedBy->getVotedFor() === $this) {
                $votedBy->setVotedFor(null);
            }
        }

        return $this;
    }

    public function getAuth(): ?string
    {
        return $this->auth;
    }

    public function setAuth(string $auth): static
    {
        $this->auth = $auth;

        return $this;
    }
}
