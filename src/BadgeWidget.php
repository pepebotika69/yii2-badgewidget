<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\Utils;
use pozitronik\helpers\ArrayHelper;
use pozitronik\helpers\ReflectionHelper;
use Throwable;
use yii\base\DynamicModel;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;

/**
 * Class BadgeWidget
 * @property-write string|array|object|callable $items Данные для обработки (строка, массив, модель, замыкание). Преобразуются в массив при обработке.
 * @property-write string $subItem Отображаемый ключ (строка => null, массив => key, модель => атрибут/свойство/переменная, замыкание => параметр). Виджет пытается просчитать его автоматически.
 * @property-write bool $useBadges Включает/отключает генерацию значков.
 * @property-write string|null $itemsSeparator Строка-разделитель между элементами. null - не использовать разделитель.
 * @property-write string|null $emptyText Текст иконки, подставляемой при отсутствии обрабатываемых данных. null - не подставлять текст.
 * @property-write bool $iconize Содержимое бейджа сокращается до псевдоиконки.
 *
 * @property-write string|callable $innerPrefix Строка, добавляемая перед текстом внутри значка. Если задано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $innerPostfix Строка, добавляемая после текста внутри значка. Если задано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @TODO @property-write string|callable $outerPrefix Строка, добавляемая перед текстом снаружи значка. Если задано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @TODO @property-write string|callable $outerPostfix Строка, добавляемая перед текстом внутри значка. Если задано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 *
 * @property-write null|string $mapAttribute Атрибут, значение которого будет использоваться как ключевое при сопоставлении элементов с массивами параметров. Если не задан, виджет попытается вычислить его самостоятельно, взяв ключевой атрибут для ActiveRecord или ключ для элемента массива.
 *
 * @property-write bool|int|callable $visible Параметр, определяющий, какие элементы будут отображены.
 *        true - будут отображены все элементы,
 *        false - будут скрыты все элементы (предполагается взаимодействие через $addon)
 *        int - будет отображено указанное число первых элементов,
 *        callable - будет вызвана функция, в которой параметром будет передан ключ элемента (если есть). Логический результат выполнения этой функции определяет отображение элемента.
 *
 * @property-write array|callable $options HTML-опции для каждого значка по умолчанию. Если задано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть массив опций для этого элемента.
 * @property-write array|false $urlScheme Схема подстановки значений атрибутов элемента в генерируемую ссылку, например:
 *        $item = {"key" => 1, "value" => 100, "property" => "propertyData", "arrayParameter" => ["a" => 10, "b" => 20, "c" => 30]]}
 *        UrlOptions->scheme = ['site/index', 'id' => 'value', 'param1' => 'property', 'param2' => 'non-property', 'param3' => 'arrayParameter']
 * Получим набор параметров ссылки для элемента:
 *        ['site/index', 'id' => 100, 'param1' => 'propertyData', 'param2' => 'non-property', 'param3[a]' => 10, 'param3[b]' => 20, 'param3[c]' => 30]
 * false - элементы не превращаются в ссылки.
 *
 * @property-write string[]|callable|false|string $tooltip Настройки для всплывающей подсказки.
 *        false - всплывающая подсказка не используется,
 *        string - одна подсказка на все элементы,
 *        string[] - массив подсказок, сопоставляемый с элементами по ключу,
 *        callable - будет вызвана функция, получающая на вход ключ элемента (если есть), которая должна вернуть строку с текстом подсказки.
 * @property-write bool $bootstrapTooltip Использование подсказок bootstrap, если false - то будут использованы нативные браузерные подсказки.
 * @property-write string $tooltipPlacement Позиция появления всплывающей подсказки, см. TP_*-константы. Применяется также и для всплывающей подсказке на аддоне.
 * @property-write string $tooltipTrigger Триггер появления подсказок, см. TT_*-константы. Применяется также и для всплывающей подсказке на аддоне.
 *
 * @property bool|string|callable $addon Элемент, используемый для отображения информации о скрытых значках и разворачивании всего списка. Значения:
 *        false - не будет показан ни в каких случаях,
 *        true - будет показан сгенерированный элемент, отображающий информацию о скрытых значках (при их наличии),
 *        string - будет отображена заданная строка,
 *        callable - будет вызвана функция, получающая на вход первым параметром количество видимых элементов, вторым параметром количество скрытых элементов, которая должна вернуть строку с содержимым.
 * @property array|callable|null $addonOptions HTML-опции аддона. Если null - копируется из $options
 * @property callable|false|string $addonTooltip Настройки всплывающей подсказки на аддоне.
 *        false - всплывающая подсказка не используется,
 *        string - текстовая подсказка,
 *        callable - будет вызвана функция, получающая на вход массив всех значений всех элементов баз всякого дополнительного форматирования, которая должна вернуть строку с текстом подсказки.
 */
class BadgeWidget extends CachedWidget {
	/*Константы позиционирования подсказки*/
	public const TP_TOP = 'top';
	public const TP_RIGHT = 'right';
	public const TP_BOTTOM = 'bottom';
	public const TP_LEFT = 'left';
	/*Константы триггеров подсказки*/
	public const TT_HOVER = 'hover';
	public const TT_CLICK = 'click';
	public const TT_FOCUS = 'focus';

	/* Тег, используемый для генерации значков */
	private const BADGE_TAG = 'span';
	/* Классы значков (всегда добавляются, независимо от пользовательских классов)*/
	private const BADGE_CLASS = ['class' => 'badge'];
	private const ADDON_BADGE_CLASS = ['class' => 'badge addon-badge'];

	public $subItem;
	public $useBadges = true;
	public $itemsSeparator;
	public $emptyText;
	public $iconize = false;
	public $innerPrefix = '';
	public $innerPostfix = '';
	public $outerPrefix = '';
	public $outerPostfix = '';
	public $mapAttribute;
	public $visible = 3;
	public $addon = true;

	public $options = self::BADGE_CLASS;
	public $addonOptions = self::ADDON_BADGE_CLASS;

	public $tooltip = false;
	public $bootstrapTooltip = true;
	public $tooltipPlacement = self::TP_TOP;
	public $tooltipTrigger = self::TT_HOVER;
	public $urlScheme = false;

	/** @var array */
	private $_items = [];

	/* Необработанные значения атрибутов, нужны для вывода подсказки в тултип на элементе аддона */
	private $_rawResultContents = [];

	/**
	 * Функция инициализации и нормализации свойств виджета
	 */
	public function init():void {
		parent::init();
		BadgeWidgetAssets::register($this->getView());
		if ($this->bootstrapTooltip) {
			$this->view->registerJs("$('[data-toggle=\"tooltip\"]').tooltip()");
		}
	}

	/**
	 * @return array
	 */
	public function getItems():array {
		return $this->_items;
	}

	/**
	 * @param array|callable|object|string $items
	 */
	public function setItems($items):void {
		$this->_items = $items;
		if (ReflectionHelper::is_closure($this->_items)) $this->_items = call_user_func($this->_items);
		if (!is_array($this->_items)) $this->_items = [$this->_items];

	}

	/**
	 * Преобразует каждый перечисляемый объект в модель для внутреннего использования
	 * @param null|int $index
	 * @param $item
	 * @return Model
	 */
	private function prepareItem(?int $index, $item):Model {
		if (!is_object($item)) {
			if (is_array($item)) {
				return new DynamicModel($item);
			}
			return new DynamicModel([
				'id' => $index,
				$this->subItem => $item
			]);
		}
		return $item;
	}

	/**
	 * Вытаскивает из подготовленной модели значение для отображения
	 * @param Model $item
	 * @param string $mapAttribute
	 * @return string
	 * @throws Throwable
	 */
	private function prepareValue(Model $item, string $mapAttribute):string {
		$itemValue = ArrayHelper::getValue($item, $this->subItem);/*Текстовое значение значка*/
		$this->_rawResultContents[$item->{$mapAttribute}] = $itemValue;
		$prefix = (is_callable($this->innerPrefix))?call_user_func($this->innerPrefix, $item->{$mapAttribute}):$this->innerPrefix;
		$postfix = (is_callable($this->innerPostfix))?call_user_func($this->innerPostfix, $item->{$mapAttribute}):$this->innerPostfix;

		return $prefix.$itemValue.$postfix;
	}

	/**
	 * Возвращает набор параметров для конкретного элемента.
	 * @param Model $item
	 * @param string $mapAttribute
	 * @param array|callable $options
	 * @return array
	 * @throws Throwable
	 */
	private static function PrepareItemOption(Model $item, string $mapAttribute, $options):array {
		return (is_callable($options))?$options($item->{$mapAttribute}):$options;
	}

	/**
	 * Генерирует единичный значок
	 * @param string $text Содержимое значка
	 * @param array $elementOptions
	 * @return string
	 */
	private function prepareBadge(string $text, array $elementOptions):string {
		if ($this->useBadges) {
			Html::addCssClass($elementOptions, self::BADGE_CLASS);
			return Html::tag(self::BADGE_TAG, $text, $elementOptions);
		}
		return $text;
	}

	/**
	 * Генерирует всё отображение значков, вычисляя видимые/скрытые элементы и добавляя, при необходимости, дополнительный значок.
	 * @param string[] $visibleBadges Массив с содержимым значков, на выходе - массив отображаемых элементов
	 * @param string[] $hiddenBadges Массив скрытых элементов
	 * @throws InvalidConfigException
	 */
	public function prepareBadges(array &$visibleBadges, array &$hiddenBadges = []):void {
		if (true === $this->visible) return;/*Если отображаются все значки, обработка не требуется*/
		if (false === $this->visible) {/*Если не отображается ни одного значка*/
			$hiddenBadges = $visibleBadges;
			$visibleBadges = [];
			return;
		}
		if (is_int($this->visible)) {
			if (count($visibleBadges) > $this->visible) {
				$visibleArray = array_slice($visibleBadges, 0, $this->visible, true);
				$hiddenBadges = array_diff_key($visibleBadges, $visibleArray);
				$visibleBadges = $visibleArray;
				return;
			}
			return;
		}
		if (is_callable($this->visible)) {
			$visibleArray = [];
			foreach ($visibleBadges as $itemKey => $itemValue) {
				if (true === call_user_func($this->visible, $itemKey)) {
					$visibleArray[$itemKey] = $itemValue;
				} else {
					$hiddenBadges[$itemKey] = $itemValue;
				}
			}
			$visibleBadges = $visibleArray;
			return;

		}
		throw new InvalidConfigException('Wrong type for "visible" parameter');
	}

	/**
	 * @param int $visibleElementsCount
	 * @param int $hiddenElementsCount
	 * @return string
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	private function prepareAddonBadge(int $visibleElementsCount, int $hiddenElementsCount):string {
		if (true === $this->addon) {
			$addonText = "...ещё {$hiddenElementsCount}";
		} elseif (is_string($this->addon)) {
			$addonText = $this->addon;
		} elseif (is_callable($this->addon)) {
			$addonText = call_user_func($this->addon, $visibleElementsCount, $hiddenElementsCount);
		} elseif (false !== $this->addon) {
			throw new InvalidConfigException('Wrong type for "addon" parameter');
		} else {
			return '';
		}
		$addonOptions = self::PrepareItemOption($this->prepareItem(-1, $addonText), 'id', $this->addonOptions??$this->options);
		Html::addCssClass($addonOptions, self::ADDON_BADGE_CLASS);
		return Html::tag(self::BADGE_TAG, $addonText, $addonOptions);
	}

	/**
	 * Вычисляет атрибут сопоставления
	 * @param Model $item
	 * @return string
	 */
	public function prepareMapAttribute(Model $item):string {
		if (null === $this->mapAttribute) {
			if ($item->hasProperty('primaryKey')) {/*assume ActiveRecord*/
				return 'primaryKey';
			}
			if ($item->hasProperty('id')) {/*assume generated DynamicModel*/
				return 'id'; /*todo: запоминать преобразование в PrepareItem для ускорения проверки*/
			}
		}
		return $this->mapAttribute;
	}

	/**
	 * Добавляет элементу bootstrap-tooltip
	 * @param Model $item
	 * @param array $itemOptions
	 * @param string $mapAttribute
	 * @return array
	 * @throws Throwable
	 */
	public function prepareTooltip(Model $item, string $mapAttribute, array $itemOptions):array {
		if (false === $this->tooltip) return $mapAttribute;
		$tooltip = $this->tooltip;
		if (is_callable($tooltip)) {
			$tooltip = $tooltip($item->{$mapAttribute});
		} elseif (is_array($tooltip)) {
			$tooltip = ArrayHelper::getValue($tooltip, $item->{$mapAttribute});
		}

		$tooltipOptions = $this->bootstrapTooltip?[
			'class' => 'add-tooltip',
			'data-toggle' => 'tooltip',
			'data-trigger' => $this->tooltipTrigger,
			'data-original-title' => $tooltip,
			'title' => $tooltip,
			'data-placement' => $this->tooltipPlacement
		]:[
			'title' => $tooltip
		];

		return ArrayHelper::mergeImplode(' ', $itemOptions, $tooltipOptions);
	}

	/**
	 * @param Model $item
	 * @param string $content
	 * @return string
	 * @throws Throwable
	 */
	public function prepareUrl(Model $item, string $content):string {
		if (false === $this->urlScheme) return $content;
		$arrayedParameters = [];
		$currentLinkScheme = $this->urlScheme;
		array_walk($currentLinkScheme, static function(&$value, $key) use ($item, &$arrayedParameters) {//подстановка в схему значений из модели
			if (is_array($value)) {//value passed as SomeParameter => [a, b, c,...] => convert to SomeParameter[1] => a, SomeParameter[2] => b, SomeParameter[3] => c
				foreach ($value as $index => $item) {
					$arrayedParameters["{$key}[{$index}]"] = $item;
				}
			} elseif ($item->hasProperty($value) && false !== $attributeValue = ArrayHelper::getValue($item, $value, false)) $value = $attributeValue;

		});
		if ([] !== $arrayedParameters) $currentLinkScheme = array_merge(...$arrayedParameters);//если в схеме были переданы значения массивом, включаем их разбор в схему
		return Html::a($content, $currentLinkScheme);
	}

	/**
	 * Функция возврата результата рендеринга виджета
	 * @return string
	 * @throws Throwable
	 */
	public function run():string {
		$badges = [];

		/**
		 * Из переданных данных собираем весь массив отображаемых значков, с полным форматированием.
		 * Это нужно потому, что:
		 *    1) отображение может быть свёрнуто на лету без подгрузок.
		 *    2) невидимые значения могут быть видны в подсказках
		 */
		foreach ($this->items as $index => $item) {
			if (null === $item) continue;

			$item = $this->prepareItem($index, $item);
			$mapAttribute = $this->prepareMapAttribute($item);
			$itemValue = $this->prepareValue($item, $mapAttribute);

			if ($this->iconize) $itemValue = Utils::ShortifyString($itemValue);
			/*Добавление ссылки к элементу*/
			$itemValue = $this->prepareUrl($item, $itemValue);
			$itemOptions = self::PrepareItemOption($item, $mapAttribute, $this->options);
			$itemOptions = $this->prepareTooltip($item, $mapAttribute, $itemOptions);
			$badges[$item->{$mapAttribute}] = $this->prepareBadge($itemValue, $itemOptions);
		}
		/*Из переданных данных не удалось собрать массив, показываем выбранную заглушку*/
		if ([] === $badges && null !== $this->emptyText) {
			return self::widget([
				'items' => $this->emptyText,//todo: проверить, что будет при $emptyText массивом или замыканием
				'iconize' => $this->iconize,
				'innerPrefix' => $this->innerPrefix,
				'innerPostfix' => $this->innerPostfix,
				'outerPrefix' => $this->outerPrefix,
				'outerPostfix' => $this->outerPostfix,
				'options' => $this->options,
				'urlScheme' => $this->urlScheme,
				'tooltip' => $this->tooltip
			]);
		}
		if ($this->useBadges) {
			$hiddenBadges = [];
			$this->prepareBadges($badges, $hiddenBadges);
			if ([] !== $hiddenBadges) {
				$badges[] = $this->prepareAddonBadge(count($badges), count($hiddenBadges));
			}
		}

		return implode($this->itemsSeparator??'', $badges);

	}

}
