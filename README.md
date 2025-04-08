# Bitrix24_call_in_company
Add new call app, which copy call/email in chained company

Установка:

Склонировать репозиторий `git clone https://github.com/Dev0nLee/Bitrix24_call_in_company.git`
Перейти в папку проекта `cd Bitrix24_call_in_company`
Установить зависимости `composer install`
Залить на сервер
На портале Битрикс перейти в "Разработчикам"->"Встроить виджет"->"Добавить своё действие в карточку CRM"
В поле "Генератор запросов" указать метод event.bind с параметрами `event` : `OnVoximplantCallInit` и `handler` : `https://your-domain/call/webhook`. 
Включить галочку в поле "Виджеты", ввести название виджета, URL обработчика: `https://your-domain/call-card`, место вывода виджета Карточка звонка (CALL_CARD).
Указать права CRM, Встраивание приложений, Телефония, Пользователи.
Нажать сохранить и скопировать Вебхук для вызова rest api. В .env добавить строчку `BITRIX24_WEBHOOK=your_webhook` и вставить вебхук.

# Тестирование
Запустить локальный сервер `symfony server:start`
Запустить на общедоступном сервере.
Сделать тестовый звонок: curl.exe -i -X POST -H "Content-Type:application/json" -d \`@startcall.json http://localhost:8000/call/start
Звонок должен отобразиться на портале Битрикс. Также в карточке звонка должен отобразиться виджет с информацией о контакте.
Из терминала или файла var/call_data.txt скопировать Call_id и вставить его в файл endcall.json
Завершить тестовый звонок: curl.exe -i -X POST -H "Content-Type:application/json" -d \`@endcall.json http://localhost:8000/call/finish
