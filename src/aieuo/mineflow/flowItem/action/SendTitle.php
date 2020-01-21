<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\formAPI\Form;
use pocketmine\entity\Entity;
use pocketmine\Player;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\utils\Categories;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\Input;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\Toggle;

class SendTitle extends Action {

    protected $id = self::SEND_TITLE;

    protected $name = "action.sendTitle.name";
    protected $detail = "action.sendTitle.detail";
    protected $detailDefaultReplace = ["title", "subtitle"];

    protected $category = Categories::CATEGORY_ACTION_MESSAGE;

    protected $targetRequired = Recipe::TARGET_REQUIRED_PLAYER;

    /** @var string */
    private $title;
    /** @var string */
    private $subtitle;
    /** @var string */
    private $fadeIn = "-1";
    /** @var string */
    private $stay = "-1";
    /** @var string */
    private $fadeOut = "-1";

    public function __construct(string $title = "", string $subtitle = "", int $fadeIn = -1, int $stay = -1, int $fadeOut = -1) {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->fadeIn = $fadeIn;
        $this->stay = $stay;
        $this->fadeOut = $fadeOut;
    }

    public function setTitle(string $title, string $subtitle = ""): self {
        $this->title = $title;
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getSubTitle(): string {
        return $this->subtitle;
    }

    public function setTime(int $fadeIn = -1, int $stay = -1, int $fadeOut = -1): self {
        $this->fadeIn = (string)$fadeIn;
        $this->stay = (string)$stay;
        $this->fadeOut = (string)$fadeOut;
        return $this;
    }

    public function getTime(): array {
        return [$this->fadeIn, $this->stay, $this->fadeOut];
    }

    public function isDataValid(): bool {
        return $this->getTitle() !== "" or $this->getSubTitle() !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getTitle(), $this->getSubTitle()]);
    }

    public function execute(?Entity $target, Recipe $origin): ?bool {
        if (!$this->canExecute($target)) return null;

        $title = $origin->replaceVariables($this->getTitle());
        $subtitle = $origin->replaceVariables($this->getSubTitle());
        $times = array_map(function ($time) use ($origin) {
            return $origin->replaceVariables($time);
        }, $this->getTime());

        $target->addTitle($title, $subtitle, ...$times);
        return true;
    }

    public function getEditForm(array $default = [], array $errors = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new Input("@action.sendTitle.form.title", Language::get("form.example", ["aieuo"]), $default[1] ?? $this->getTitle()),
                new Input("@action.sendTitle.form.subtitle", Language::get("form.example", ["aieuo"]), $default[2] ?? $this->getSubTitle()),
                new Toggle("@form.cancelAndBack")
            ])->addErrors($errors);
    }

    public function parseFromFormData(array $data): array {
        $errors = [];
        if ($data[1] === "" and $data[2] === "") {
            $errors = [["@form.insufficient", 1], ["@form.insufficient", 2]];
        }
        return ["status" => empty($errors), "contents" => [$data[1], $data[2], "-1", "-1", "-1"], "cancel" => $data[3], "errors" => $errors];
    }

    public function loadSaveData(array $content): ?Action {
        if (!isset($content[1])) return null;
        $this->setTitle($content[0], $content[1]);
        if (isset($content[4])) {
            $this->setTime($content[2], $content[3], $content[4]);
        }
        return $this;
    }

    public function serializeContents(): array {
        return array_merge([$this->getTitle(), $this->getSubTitle()], $this->getTime());
    }
}