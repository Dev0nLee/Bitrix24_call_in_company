# Bitrix24_call_in_company
Add new call app, which copy call/email in chained company

Установка:

1. Склонировать репозиторий `git clone https://github.com/Dev0nLee/Bitrix24_call_in_company.git`
2. Перейти в папку проекта `cd Bitrix24_call_in_company`
3. Установить зависимости `composer install`
4. Залить на сервер
5. На портале Битрикс перейти в "Разработчикам"->"Встроить виджет"->"Добавить своё действие в карточку CRM"
6. В поле "Генератор запросов" указать метод event.bind с параметрами `event` : `OnVoximplantCallInit` и `handler` : `https://your-domain/call/webhook`. 
7. Включить галочку в поле "Виджеты", ввести название виджета, URL обработчика: `https://your-domain/call-card`, место вывода виджета Карточка звонка (CALL_CARD).
8. Указать права CRM, Встраивание приложений, Телефония, Пользователи.
9. Нажать сохранить и скопировать Вебхук для вызова rest api. В .env добавить строчку `BITRIX24_WEBHOOK=your_webhook` и вставить вебхук.

# Тестирование
1. Запустить локальный сервер `symfony server:start`
2. Запустить на общедоступном сервере.
3. Сделать тестовый звонок: ```curl.exe -i -X POST -H "Content-Type:application/json" -d \`@startcall.json http://localhost:8000/call/start```
4. Звонок должен отобразиться на портале Битрикс. Также в карточке звонка должен отобразиться виджет с информацией о контакте.
5. Из терминала или файла var/call_data.txt скопировать Call_id и вставить его в файл endcall.json
6. Завершить тестовый звонок: ```curl.exe -i -X POST -H "Content-Type:application/json" -d \`@endcall.json http://localhost:8000/call/finish```
