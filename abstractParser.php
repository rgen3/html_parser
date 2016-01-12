<?php

/**
 * Класс для парсинга html документов
 *
 * Class abstractParser
 */

abstract class abstractParser {

    /**
     * Ссылка на папку для сохранения изображений
     * @var string
     */
    protected $sImageDestination;

    /**
     * Базовц url для парсинга
     * @var string
     */
    protected $sSiteBaseUrl;

    /**
     * Базовая дирректория, где находится скрипт
     * @var string
     */
    protected $sBaseDir;
    /**
     * Ресурс файла лога для записи
     * @var Resource
     */
    protected $rLogFile;
    /**
     * Разделитель для csv
     * @var string
     */
    protected $sCSVDelimeter = ';';

    /**
     * Массив настроек tidy
     * @var array
     */
    private $aTidyOptions;

    public function __construct()
    {
        $this->setTidyOptions();
    }

    protected function __destruct()
    {
        fclose($this->rLogFile);
    }

    /**
     * Создает DOMDocument для обработки страницы
     *
     * @param string $sHTML строка
     * @param bool|false $bUseTidy
     * @param array $aTidyConfigs
     * @return DOMDocument
     */
    public function createPageDom(&$sHTML, $bUseTidy = false, $aTidyConfigs = array())
    {
        try
        {
            libxml_use_internal_errors(true);

            $oDOM = new DOMDocument('1.0', 'windows-1251');
            $oDOM->strictErrorChecking = false;
            $oDOM->recover = true;

            if ($bUseTidy)
            {
                $sHTML = $this->tidyHTML($sHTML, $aTidyConfigs);
            }

            $oDOM->loadHTML($sHTML);
            libxml_clear_errors();
            return $oDOM;
        }
        catch(Exception $e)
        {
            $this->writeToErrorLog("Ошибка в создании DOM элемента");
        }
    }

    /**
     * Получаем контент страницы через CURL
     * или file_get_contents
     *
     * @param $sPageURL
     * @param bool|true $bUseCURL
     * @param array $aCURLOptions
     * @return bool|mixed|void
     */
    public function getPageContent($sPageURL, $bUseCURL = true, array $aCURLOptions = array())
    {
        if ($bUseCURL)
        {
            $content = $this->getPageContentCURL($sPageURL, $aCURLOptions);
        }
        else
        {
            $content = $this->getPageContentNoCURL($sPageURL);
        }

        return $content;
    }

    /**
     * Получаем ноду по имени класса
     *
     * @param DOMDocument $oDOM
     * @param $sClassName
     * @return DOMNodeList
     */
    public function getNodesByClassName(DOMDocument &$oDOM, $sClassName)
    {
        $oXPath = new DOMXPath($oDOM);
        $oNodes = $oXPath->query("//*[@class='{$sClassName}']");

        unset($oXPath);

        return $oNodes;
    }

    /**
     * Возвращаем DOMNodeList для указанного
     * XPath выражения (обязательный префикс //)
     * Если указан параметр $oContextNode, поиск
     * будет производится в пределах указанного oNode объекта
     * Указание параметра $iNubmer вернет ноду по номеру
     *
     * @param DOMDocument $oDOM
     * @param $sXPath
     * @param null|DOMNode $oContextNode
     * @param bool|false $iNumber
     * @return DOMNode|DOMNodeList
     */
    public function getNodesByXPath(DOMDocument &$oDOM, $sXPath, $oContextNode = null, $iNumber = false)
    {
        $oXPath = new DOMXPath($oDOM);

        if ($oContextNode)
        {
            $oNodes = $oXPath->query("$sXPath", $oContextNode);
        }
        else
        {
            $oNodes = $oXPath->query("$sXPath");
        }

        if ($iNumber !== false)
        {
            $oNodes = $oNodes->item($iNumber);
        }

        unset($oXPath);

        return $oNodes;
    }

    /**
     * Возвращает массив указанного аттрибутов
     * в нодах
     *
     * @param DOMNodeList $oNodes
     * @param $sAttrName
     * @return array
     */
    public function getAttributes(DOMNodeList &$oNodes, $sAttrName)
    {
        $aResult = array();
        foreach ($oNodes as $oNode)
        {
            $aResult[] = $this->getAttribute($oNode, $sAttrName);
        }
        return $aResult;
    }

    /**
     * Возвращает аттрибут из ноды
     *
     * @param DOMElement $oNode
     * @param $sAttrName
     * @return string
     */
    public function getAttribute(DOMElement &$oNode, $sAttrName)
    {
        return $oNode->getAttribute($sAttrName);
    }

    /**
     * TODO: получение ноды по id
     *
     * @param DOMDocument $oDOM
     * @param $id
     */
    public function getNodeById(DOMDocument &$oDOM, $id)
    {
    }

    /**
     * Записываем строку в csv фаил
     *
     * @param array $aValues
     */
    public function writeCSV(array $aValues)
    {
        fputcsv($this->rCSVHandler, $aValues, $this->sCSVDelimeter);
    }

    /**
     * Сохраняем картинку из указанного url
     * При указании параметра $bOverrideForce изображение
     * будет перезаписанно, если существует
     *
     * По умолчанию используется CURL, если передать значение
     * false в параметр $bUseCURL - контент изображения будет
     * получаться через file_get_contents
     *
     * $aCURLOptions - массив параметров для CURL
     *
     * @param string $sUrl
     * @param bool|false $bOverrideForce
     * @param bool|true $bUseCURL
     * @param array $aCURLOptions
     * @return string
     * @throws Exception
     */
    public function saveImage($sUrl, $bOverrideForce = false, $bUseCURL = true, $aCURLOptions = array())
    {
        $sImageName = explode('/', $sUrl);
        $sImageName = array_pop($sImageName);

        $sFilePath  = $this->sImageDestination . DIRECTORY_SEPARATOR . $sImageName;

        if (!$this->isFileExixts($sFilePath) || $bOverrideForce)
        {
            if (!$this->isFolderExists($this->sImageDestination))
            {
                $this->makeFolderRecursive($this->sImageDestination);
            }

            $sImageContents = $this->getPageContent($sUrl, $bUseCURL, $aCURLOptions);
            $this->writeToFile($sImageName, $this->sImageDestination, $sImageContents, 'w+');
        }

        return $sFilePath;
    }

    /**
     * Tidy сжирает память, не рекомендуется пользоваться данной возможностью
     *
     * TODO: разобраться с проблемами памяти в tydy_parse_string
     *
     * @param $sHTML
     * @param array $aOpts
     * @return bool|string
     */
    protected function tidyHTML($sHTML, $aOpts = array())
    {
        if (!empty($aOpts))
        {
            $this->setTidyOptions($aOpts);
        }

        $tidy = new tidy();
        $sHTML = $tidy->parseString($sHTML);
        $sHTML = $tidy->repairString($sHTML);

        $sHTML = tidy_parse_string($sHTML, $this->aTidyOptions);
        return $sHTML;
    }

    /**
     * Получаем html контент указанного элемента
     *
     * @param DOMElement $oElement
     * @return string
     */
    protected function innerHTML(DOMElement &$oElement)
    {
        $innerHTML = "";
        $oNodes  = $oElement->childNodes;
        foreach ($oNodes as $oNode)
        {
            $innerHTML .= $oNode->ownerDocument->saveHTML();
        }

        return $innerHTML;
    }

    /**
     * TODO: импорт в DOMDocument из DOMElement
     *
     * @param DOMElement $oNode
     */
    protected function getDOMDocumentFromNodeElement(DOMElement &$oNode)
    {

    }

    /**
     * Проверяет существует ли указанная папка
     *
     * @param string $sPath
     * @param bool|false $sCustomPath
     * @return bool
     */
    protected function isFolderExists($sPath, $sCustomPath = false)
    {
        return (bool)(file_exists($sPath) && is_dir($sPath));
    }

    /**
     * Проверяет существует ли указанный фаил
     *
     * @param string $sPath
     * @return bool
     */
    protected function isFileExixts($sPath)
    {
        return (bool)(file_exists($sPath) && is_file($sPath));
    }

    /**
     * Сохраняем фаил по указанному пути,
     * если фаил с таким именем уже существует -
     * будет создан еще один фаил, с суффиксом _\d+
     *
     * @param string $sFileName
     * @param string $sExtension
     * @param string $sPath
     * @param string $sData
     * @param int $iMode
     * @throws Exception
     */
    private function saveFile($sFileName, $sExtension, $sPath, $sData, $iMode = 0777)
    {
        if (!$this->isFolderExists($sPath))
        {
            $this->makeFolderRecursive($sPath, $iMode);
        }

        $sFullFileName = "{$sFileName}.{$sExtension}";
        $i = 0;
        while ($this->isFileExists($sFullFileName))
        {
            preg_replace("/_\d+$/", "", $sFileName);
            $sFileName .= "_{$i}";
            $sFullFileName = "{$sFileName}.{$sExtension}";
        }

        $this->writeToFile($sFullFileName, $sPath, $sData);
    }

    /**
     * TODO: получение расширения файла из его имени
     *
     */
    private function getFileExtension()
    {

    }

    /**
     * Устанавливаем опции для tydy
     *
     * @param bool|false $aOpts
     */
    private function setTidyOptions($aOpts = false)
    {
        if ($aOpts === false) {

            $this->aTidyOptions = array(
                'indent' => TRUE,
                'output-xhtml' => TRUE,
                'wrap' => 200,
                'input-encoding' => 'windows-1251',
                'char-encoding' => 'windows-1251',
            );
        }
        else
        {
            $this->aTidyOptions = $aOpts;
        }
    }

    /**
     * Рекурсивное создание дирректории
     *
     * При ошибках выбрасывается исключение,
     * которое записывается в фаил лога
     *
     * @param string $sPath
     * @param int $iMode
     * @param null $context
     * @throws Exception
     */
    private function makeFolderRecursive($sPath, $iMode = 0777, $context = null)
    {
        try
        {
            if (!$this->isValidPath($sPath))
            {
                throw new Exception("Некорректный путь! {$sPath}");
            }

            if (!mkdir($sPath, $iMode, true))
            {
                throw new Exception("Не удалось создать дирректорию! {$sPath} {$error}");
            }
        }
        catch(Ecxeption $e)
        {
            $this->writeToErrorLog($e->getMessage);
        }
    }

    /**
     * Получаем базовый путь к дирректории
     *
     * @param $sPath
     * @param $sCustomPath
     * @return string
     */
    private function getPathBase($sPath, $sCustomPath)
    {
        if ($sCustomPath)
        {
            $sPath = rtrim($sPath, '/');
            $sPath = $this->sBaseDir . DIRECTORY_SEPARATOR . $sPath;
        }

        return $sPath;
    }

    /**
     * Открывает фаил для последующей записи,
     * Если файла не существует пытаемся создать
     *
     * @param $sFileName
     * @param $sPath
     * @throws Exception
     */
    protected function openFile($sFileName, $sPath, $sMode = 'w')
    {
        if (!$this->isFolderExists($sPath))
        {
            $this->makeFolderRecursive($sPath);
        }

        $sFullPath = $sPath . '/' . $sFileName;

        $rHandler = fopen($sFullPath, $sMode);
        return $rHandler ;
    }

    /**
     * Проверяем валидность url и пути к дирректории
     *
     * @param string $sPath
     * @param bool|false $isUrl
     * @return bool
     */
    protected function isValidPath($sPath, $isUrl = false)
    {
        try
        {
            if ($isUrl && !filter_var($sPath, FILTER_VALIDATE_URL))
            {
                throw new Exception("Неправильный формат url $sPath");
            }

            // TODO: Валидация путей
            $result = true;
        }
        catch(Exception $e)
        {
            $this->writeToErrorLog();
            $result = false;
        }

        return $result;
    }

    /**
     * Создаем csv фаил для записи парсинга
     * ресурс файла будет храниться в $this->rCSVHandler
     */
    protected function createCSV()
    {
        $this->csv = $this->sBaseDir . '/csv/' . 'file.csv';
        $this->rCSVHandler = $this->openFile('file.csv', $this->sBaseDir . '/csv/');
    }

    /**
     * Создаем фаил для записи лога
     */
    protected function createLog()
    {
        $sPath = $this->sBaseDir . '/log/';
        $this->log = 'log.txt';
        $this->rLogFile = $this->openFile($this->log, $sPath, 'a');
    }

    /**
     * Записываем строку в фаил логаа
     *
     * @param $sMsg
     */
    protected function writeToErrorLog($sMsg)
    {
        $sPrepared = date("r") . " " . $sMsg . PHP_EOL;
        fwrite($this->rLogFile, $sPrepared);
    }

    /**
     * Получаем контент страницы через CURL
     *
     * @param string $sUrl
     * @param array $aParams
     * @return bool|mixed
     */
    private function getPageContentCURL($sUrl, array $aParams = array())
    {
        try
        {
            $rCURL = curl_init();
            curl_setopt($rCURL, CURLOPT_URL, $sUrl);

            if (!array_key_exists(CURLOPT_RETURNTRANSFER, $aParams))
            {
                $aParams[CURLOPT_RETURNTRANSFER] = 1;
            }

            curl_setopt_array($rCURL, $aParams);

            $mResult = curl_exec($rCURL);

            if (curl_errno($rCURL))
            {
                throw new Exception(curl_error($rCURL));
            }

            curl_close($rCURL);
        }
        catch (Exception $e)
        {
            $this->writeToErrorLog("CURL error ", $e->getMessage());
            $mResult = false;
        }

        return $mResult;
    }

    /**
     * Получаем контент документа через file_get_contents
     *
     * @param sting $sUrl
     */
    private function getPageContentNoCURL($sUrl)
    {
        try
        {
            $sContent = file_get_contents($sUrl);
            if (!$sContent)
            {
                throw new Exception("Не удалось получить содержимое документа по адресу {$sUrl}");
            }
        }
        catch (Exception $e)
        {
            $this->writeToErrorLog($e->getMessage());
        }

    }

    /**
     * Записываем данные в указанный фаил
     *
     * @param sting $sFileName
     * @param sting $sFilePath
     * @param sting $sData
     * @param string $sMode
     */
    private function writeToFile($sFileName, $sFilePath, $sData, $sMode = 'w')
    {
        try
        {
            $sFilePath = rtrim($sFilePath, DIRECTORY_SEPARATOR);
            $sFileName = ltrim($sFileName, DIRECTORY_SEPARATOR);
            $sFile = $sFilePath . DIRECTORY_SEPARATOR . $sFileName;

            if ($this->isFileExixts($sFile) &&!is_readable($sFile))
            {
                throw new Exception("Фаил {$sFile} недоступен для чтения или не существует!");
            }

            $rFile = fopen($sFile, $sMode);
            fwrite($rFile, $sData);

            fclose($rFile);
        }
        catch(Exception $e)
        {
            $this->writeToErrorLog("Ошибка записи в фаил {$sFileName}");
        }
    }
}