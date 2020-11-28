<?php


namespace aieuo\mineflow\ui\customForm;


use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\Button;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\Dropdown;
use aieuo\mineflow\formAPI\element\Input;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\formAPI\ListForm;
use aieuo\mineflow\formAPI\ModalForm;
use aieuo\mineflow\Main;
use aieuo\mineflow\trigger\form\FormTrigger;
use aieuo\mineflow\trigger\TriggerHolder;
use aieuo\mineflow\ui\HomeForm;
use aieuo\mineflow\ui\MineflowForm;
use aieuo\mineflow\ui\RecipeForm;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\utils\Session;
use pocketmine\Player;

class CustomFormForm {

    public function sendMenu(Player $player, array $messages = []): void {
        (new ListForm("@form.form.menu.title"))
            ->addButtons([
                new Button("@form.back"),
                new Button("@form.add"),
                new Button("@form.edit"),
                new Button("@form.form.menu.formList"),
            ])->onReceive(function (Player $player, int $data) {
                switch ($data) {
                    case 0:
                        (new HomeForm)->sendMenu($player);
                        break;
                    case 1:
                        $this->sendAddForm($player);
                        break;
                    case 2:
                        $this->sendSelectForm($player);
                        break;
                    case 3:
                        $this->sendFormList($player);
                        break;
                }
            })->addMessages($messages)->show($player);
    }

    public function sendAddForm(Player $player, array $defaults = [], array $errors = []): void {
        (new CustomForm("@form.form.addForm.title"))
            ->setContents([
                new Input("@customForm.name", "", $defaults[0] ?? "", true),
                new Dropdown("@form.form.addForm.type", [
                    Language::get("customForm.modal"),
                    Language::get("customForm.form"),
                    Language::get("customForm.custom_form"),
                ]),
                new CancelToggle(),
            ])->onReceive(function (Player $player, array $data) {
                if ($data[2]) {
                    $this->sendMenu($player);
                    return;
                }

                switch ($data[1]) {
                    case 0:
                        $form = new ModalForm($data[0]);
                        break;
                    case 1:
                        $form = new ListForm($data[0]);
                        break;
                    case 2:
                        $form = new CustomForm($data[0]);
                        break;
                    default:
                        $this->sendAddForm($player, $data, [["@form.insufficient", 1]]);
                        return;
                }

                $manager = Main::getFormManager();
                if ($manager->existsForm($data[0])) {
                    $newName = $manager->getNotDuplicatedName($data[0]);
                    (new MineflowForm)->confirmRename($player, $data[0], $newName,
                        function (string $name) use ($player, $form) {
                            $manager = Main::getFormManager();
                            $form->setTitle($name);
                            $manager->addForm($name, $form);
                            Session::getSession($player)->set("form_menu_prev", [$this, "sendMenu"]);
                            $this->sendFormMenu($player, $form);
                        },
                        function (string $name) use ($player, $data) {
                            $this->sendAddForm($player, $data, [[Language::get("form.form.exists", [$name]), 0]]);
                        });
                    return;
                }
                $manager->addForm($data[0], $form);
                Session::getSession($player)->set("form_menu_prev", [$this, "sendMenu"]);
                $this->sendFormMenu($player, $form);
            })->addErrors($errors)->show($player);
    }

    public function sendSelectForm(Player $player, array $default = [], array $errors = []): void {
        (new CustomForm("@form.form.select.title"))
            ->setContents([
                new Input("@customForm.name", "", $default[0] ?? "", true),
                new CancelToggle(),
            ])->onReceive(function (Player $player, array $data) {
                if ($data[1]) {
                    $this->sendMenu($player);
                    return;
                }

                $manager = Main::getFormManager();
                $name = $data[0];
                if (!$manager->existsForm($name)) {
                    $this->sendSelectForm($player, $data, [["@form.form.notFound", 0]]);
                    return;
                }

                $form = $manager->getForm($name);
                Session::getSession($player)->set("form_menu_prev", [$this, "sendSelectForm"]);
                $this->sendFormMenu($player, $form);
        })->addErrors($errors)->show($player);
    }

    public function sendFormList(Player $player): void {
        $manager = Main::getFormManager();
        $forms = $manager->getAllFormData();
        $buttons = [new Button("@form.back")];
        foreach ($forms as $form) {
            $buttons[] = new Button($form["name"].": ".Language::get("customForm.".$form["type"]));
        }

        (new ListForm("@form.form.menu.formList"))
            ->addButtons($buttons)
            ->onReceive(function (Player $player, int $data, array $forms) {
                if ($data === 0) {
                    $this->sendMenu($player);
                    return;
                }
                $data--;

                $form = $forms[$data]["form"];
                if (!($form instanceof Form)) $form = Form::createFromArray($forms[$data]["form"], $forms[$data]["name"]); // FIXME: error object?
                Session::getSession($player)->set("form_menu_prev", [$this, "sendFormList"]);
                $this->sendFormMenu($player, $form);
            })->addArgs(array_values($forms))->show($player);
    }

    public function sendFormMenu(Player $player, Form $form, array $messages = []): void {
        switch (true) {
            case $form instanceof ModalForm:
                (new CustomModalFormForm())->sendMenu($player, $form, $messages);
                break;
            case $form instanceof ListForm:
                (new CustomListFormForm())->sendMenu($player, $form, $messages);
                break;
            case $form instanceof CustomForm:
                (new CustomCustomFormForm())->sendMenu($player, $form, $messages);
                break;
        }
    }

    public function sendChangeFormTitle(Player $player, Form $form): void {
        (new CustomForm("@form.form.formMenu.changeTitle"))
            ->setContents([
                new Input("@customForm.title", "", $form->getTitle()),
                new CancelToggle(function() use($player, $form) { $this->sendFormMenu($player, $form, ["@form.cancelled"]); }),
            ])->onReceive(function (Player $player, array $data, Form $form) {
                $form->setTitle($data[0]);
                Main::getFormManager()->addForm($form->getName(), $form);
                $this->sendFormMenu($player, $form, ["@form.changed"]);
            })->addArgs($form)->show($player);
    }

    public function sendChangeFormContent(Player $player, Form $form): void {
        if (!($form instanceof ModalForm) and !($form instanceof ListForm)) return;
        (new CustomForm("@form.form.formMenu.editContent"))
            ->setContents([
                new Input("@customForm.content", "", $form->getContent()),
                new CancelToggle(function() use($player, $form) { $this->sendFormMenu($player, $form, ["@form.cancelled"]); }),
            ])->onReceive(function (Player $player, array $data, Form $form) {
                if (!($form instanceof ModalForm) and !($form instanceof ListForm)) return;

                $form->setContent($data[0]);
                Main::getFormManager()->addForm($form->getName(), $form);
                $this->sendFormMenu($player, $form, ["@form.changed"]);
            })->addArgs($form)->show($player);
    }

    public function sendChangeFormName(Player $player, Form $form, array $default = [], array $errors = []): void {
        (new CustomForm("@form.form.formMenu.changeName"))
            ->setContents([
                new Input("@customForm.name", "", $default[0] ?? $form->getName(), true),
                new CancelToggle(),
            ])->onReceive(function (Player $player, array $data, Form $form) {
                if ($data[1]) {
                    $this->sendFormMenu($player, $form, ["@form.cancelled"]);
                    return;
                }

                $manager = Main::getFormManager();
                if ($manager->existsForm($data[0])) {
                    $newName = $manager->getNotDuplicatedName($data[0]);
                    (new MineflowForm)->confirmRename($player, $data[0], $newName,
                        function (string $name) use ($player, $form) {
                            $form->setName($name);
                            $manager = Main::getFormManager();
                            $manager->removeForm($name);
                            $manager->addForm($name, $form);
                            $this->sendFormMenu($player, $form, ["@form.changed"]);
                        },
                        function (string $name) use ($player, $form, $data) {
                            $this->sendChangeFormName($player, $form, $data, [[Language::get("customForm.exists", [$name]), 0]]);
                        });
                    return;
                }

                $manager->removeForm($form->getName());
                $form->setName($data[0]);
                $manager->addForm($data[0], $form);
                $this->sendFormMenu($player, $form, ["@form.changed"]);
            })->addArgs($form)->addErrors($errors)->show($player);
    }

    public function sendRecipeList(Player $player, Form $form, array $messages = []): void {
        $recipes = Main::getFormManager()->getAssignedRecipes($form->getName());
        (new ListForm(Language::get("form.recipes.title", [$form->getName()])))
            ->addButton(new Button("@form.back", function() use($player, $form) { $this->sendFormMenu($player, $form); }))
            ->addButton(new Button("@form.add", function() use($player, $form) { $this->sendSelectRecipe($player, $form); }))
            ->addButtonsEach($recipes, function ($keys, $name) use($player, $form) {
                return new Button($name." | ".count($keys), function() use($player, $form, $name, $keys) {
                    $this->sendRecipeMenu($player, $form, $name, $keys);
                });
            })->addMessages($messages)->show($player);
    }

    public function sendSelectRecipe(Player $player, Form $form, array $default = [], array $errors = []): void {
        (new CustomForm(Language::get("form.recipes.add", [$form->getName()])))
            ->setContents([
                new Input("@form.recipe.recipeName", "", $default[0] ?? "", true),
                new CancelToggle(),
            ])->onReceive(function (Player $player, array $data, Form $form) {
                if ($data[1]) {
                    $this->sendRecipeList($player, $form);
                    return;
                }

                $manager = Main::getRecipeManager();
                [$name, $group] = $manager->parseName($data[0]);
                $recipe = $manager->get($name, $group);
                if ($recipe === null) {
                    $this->sendSelectRecipe($player, $form, $data, [["@form.recipe.select.notfound", 0]]);
                    return;
                }

                $trigger = FormTrigger::create($form->getName());
                if ($recipe->existsTrigger($trigger)) {
                    $this->sendRecipeList($player, $form, ["@trigger.alreadyExists"]);
                    return;
                }
                $recipe->addTrigger($trigger);
                $this->sendRecipeList($player, $form, ["@form.added"]);
            })->addArgs($form)->addErrors($errors)->show($player);
    }

    public function sendRecipeMenu(Player $player, Form $form, string $name, array $triggers): void {
        $content = implode("\n", array_map(function (string $key) use ($form) {
            switch ($key) {
                case "":
                    return Language::get("trigger.form.receive");
                case "close":
                    return Language::get("trigger.form.close");
                default:
                    if ($form instanceof ListForm) {
                        $button = $form->getButtonById($key);
                        return Language::get("trigger.form.button", [$button instanceof Button ? $button->getText() : ""]);
                    }
                    return "";
            }
        }, $triggers));

        (new ListForm(Language::get("form.recipes.title", [$form->getName()])))
            ->setContent($content)
            ->setButtons([
                new Button("@form.back"),
                new Button("@form.edit")
            ])->onReceive(function (Player $player, int $data) use($form, $name, $triggers) {
                if ($data === 0) {
                    $this->sendRecipeList($player, $form);
                } elseif ($data === 1) {
                    Session::getSession($player)->set("recipe_menu_prev", function() use($player, $form, $name, $triggers) {
                        $this->sendRecipeMenu($player, $form, $name, $triggers);
                    });
                    [$name, $group] = Main::getRecipeManager()->parseName($name);
                    $recipe = Main::getRecipeManager()->get($name, $group);
                    (new RecipeForm())->sendTriggerList($player, $recipe);
                }
            })->show($player);
    }

    public function sendConfirmDelete(Player $player, Form $form): void {
        (new ModalForm(Language::get("form.recipe.delete.title", [$form->getName()])))
            ->setContent(Language::get("form.delete.confirm", [$form->getName()]))
            ->onYes(function() use($player, $form) {
                Main::getFormManager()->removeForm($form->getName());
                $this->sendMenu($player, ["@form.deleted"]);
            })->onNo(function () use($player ,$form) { $this->sendFormMenu($player, $form, ["@form.cancelled"]); })
            ->setButton2("@form.no")
            ->show($player);
    }

    public function onReceive(Player $player, $data, Form $form): void {
        $holder = TriggerHolder::getInstance();
        $trigger = FormTrigger::create($form->getName());
        $variables = $trigger->getVariables($form, $data);
        if ($holder->existsRecipe($trigger)) {
            $recipes = $holder->getRecipes($trigger);
            $recipes->executeAll($player, $variables);
        }
        switch ($form) {
            case $form instanceof ModalForm:
                /** @var bool $data */
                $trigger->setSubKey($data ? "1" : "2");
                if ($holder->existsRecipe($trigger)) {
                    $recipes = $holder->getRecipes($trigger);
                    $recipes->executeAll($player, $variables);
                }
                break;
            case $form instanceof ListForm:
                /** @var int $data */
                $button = $form->getButton($data);
                $trigger->setSubKey($button->getUUID());
                if ($holder->existsRecipe($trigger)) {
                    $recipes = $holder->getRecipes($trigger);
                    $recipes->executeAll($player, $variables);
                }
                break;
        }
    }

    public function onClose(Player $player, Form $form): void {
        $holder = TriggerHolder::getInstance();
        $trigger = FormTrigger::create($form->getName(), "close");
        if ($holder->existsRecipe($trigger)) {
            $recipes = $holder->getRecipes($trigger);
            $recipes->executeAll($player);
        }
    }
}
