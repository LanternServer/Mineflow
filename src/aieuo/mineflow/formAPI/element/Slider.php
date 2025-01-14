<?php

namespace aieuo\mineflow\formAPI\element;

use aieuo\mineflow\formAPI\element\mineflow\SliderPlaceholder;
use aieuo\mineflow\utils\Language;

class Slider extends Element {

    protected string $type = self::ELEMENT_SLIDER;

    private float $min;
    private float $max;
    private float $step;
    private float $default;

    public function __construct(string $text, float $min, float $max, float $step = 1.0, float $default = null) {
        parent::__construct($text);
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;
        $this->default = $default ?? $min;
    }

    public function setMin(float $min): self {
        $this->min = $min;
        return $this;
    }

    public function getMin(): float {
        return $this->min;
    }

    public function setMax(float $max): self {
        $this->max = $max;
        return $this;
    }

    public function getMax(): float {
        return $this->max;
    }

    public function setStep(float $step): self {
        $this->step = $step;
        return $this;
    }

    public function getStep(): float {
        return $this->step;
    }

    public function setDefault(float $default): self {
        $this->default = $default;
        return $this;
    }

    public function getDefault(): float {
        return $this->default;
    }

    public function jsonSerialize(): array {
        if ($this->min > $this->max) {
            [$this->min, $this->max] = [$this->max, $this->min];
        }
        if ($this->default === null or $this->default < $this->min) {
            $this->default = $this->min;
        }
        return [
            "type" => $this->type,
            "text" => Language::replace($this->extraText).$this->reflectHighlight(Language::replace($this->text)),
            "min" => $this->min,
            "max" => $this->max,
            "step" => $this->step,
            "default" => $this->default,
        ];
    }

    public static function fromSerializedArray(array $data): ?self {
        if (!isset($data["text"]) or !isset($data["min"]) or !isset($data["max"])) return null;

        if (isset($data["mineflow"]["placeholder"])) {
            return SliderPlaceholder::fromSerializedArray($data);
        }

        if (!isset($data["min"]) or !isset($data["max"])) return null;
        return new Slider($data["text"], $data["min"], $data["max"], $data["step"] ?? 1, $data["default"] ?? null);
    }
}