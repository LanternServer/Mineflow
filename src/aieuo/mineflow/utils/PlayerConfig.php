<?php

namespace aieuo\mineflow\utils;

use pocketmine\utils\Config;

class PlayerConfig extends Config {

    public function getFavorites(string $name, string $type): array {
        return $this->getNested($name.".favorite.".$type, []);
    }

    public function setFavorites(string $name, string $type, array $favorites) {
        $this->setNested($name.".favorite.".$type, $favorites);
    }

    public function addFavorite(string $name, string $type, string $favorite) {
        $favorites = $this->getFavorites($name, $type);
        if (!in_array($favorite, $favorites)) {
            $favorites[] = $favorite;
        }
        $this->setFavorites($name, $type, $favorites);
    }

    public function removeFavorite(string $name, string $type, string $favorite) {
        $favorites = $this->getFavorites($name, $type);
        $favorites = array_diff($favorites, [$favorite]);
        $favorites = array_values($favorites);
        $this->setFavorites($name, $type, $favorites);
    }

    public function toggleFavorite(string $name, string $type, string $favorite) {
        $favorites = $this->getFavorites($name, $type);
        if (in_array($favorite, $favorites)) {
            $this->removeFavorite($name, $type, $favorite);
        } else {
            $this->addFavorite($name, $type, $favorite);
        }
    }

}