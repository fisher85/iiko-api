from datetime import datetime, timedelta
import json
from pathlib import Path
import pytz
import requests
import time


LATENESS_THRESHOLD_IN_MINUTES = 30
REPORT_TIMEZONE = 'Asia/Irkutsk'
DEBUG = True     # without adding a category for client and sending SMS
ACCESS_TOKEN = str()
IP = 'https://iiko.biz'
PORT = '9900'
CHAT_ID = 99999999  # channel_id
BOT_TOKEN = 'bot_token_here'
ORGANIZATION_ID = 'organization_id'
IIKO_LOGIN = 'admin'
IIKO_PASSWORD = 'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3'
IIKO_BIZ_LOGIN = 'demoDelivery'
IIKO_BIZ_PASSWORD = 'Pl1yFaKFCGvvJKi'
RESTAURANT = 'PIZZA_IS_YAMMY'
KITCHEN1 = 'first_kitchen_id'
KITCHEN2 = 'second_kitchen_id'


class Order:
    def __init__(self, order):
        self.number = order['Delivery.Number']
        self.way_duration = order['Delivery.WayDuration']
        self.phone = order['Delivery.CustomerPhone']
        self.username = order['Delivery.CustomerName']
        self.departmentid = order['Department.Id']
        self.actual_time = order['Delivery.ActualTime']
        self.print_time = order['Delivery.PrintTime']
        self.customerid = str()


def send_error(text):
    url = f'https://api.telegram.org/bot{BOT_TOKEN}/sendMessage?chat_id={CHAT_ID}&text={text}'
    r = requests.get(url)
    print(r.text, r.status_code)
    exit(0)


def process_send_request(url, method='GET', body=None):
    i = 0
    while i < 3:
        if method == 'GET':
            r = requests.get(url)
            # print(url)
        else:
            r = requests.post(url, json=body)
            # print(body)

        if r.status_code == 200:
            return r.status_code, r.text
        else:
            time.sleep(60)
            i += 1
            if i == 3:
                return r.status_code, r.text


def get_access_token():
    url = f'https://{RESTAURANT}.iiko.it/resto/api/auth?login={IIKO_LOGIN}&pass={IIKO_PASSWORD}'
    code, response = process_send_request(url)
    if code == 200:
        token = response
        print(token)
        return token
    else:
        send_error(f'Can\'t get ACCESS_TOKEN. Code {code}. Response - {response}')


def release_license(token):
    url = f'https://{RESTAURANT}.iiko.it/resto/api/logout?key={token}'
    code, response = process_send_request(url)
    if code == 200:
        print('[+] License was released')
    else:
        send_error(f'Error. Lisense wasn\'t released. Code {code}. Response - {response}')


def get_olap_data(token):
    url = f'https://{RESTAURANT}.iiko.it/resto/api/v2/reports/olap?key={token}'
    f = open(Path(__file__).parent / 'data_for_olap_requests.json', 'rb')
    olap = f.read()
    f.close()
    body = json.loads(olap)
    current_date = datetime.today()
    past_date = current_date - timedelta(days=1)
    body['filters']['OpenDate.Typed']['from'] = str(past_date.date())
    body['filters']['OpenDate.Typed']['to'] = str(past_date.date())
    code, response = process_send_request(url, method='POST', body=body)
    if code == 200:
        return response
    else:
        send_error(f'Can\'t get olap data. Code {code}. Response - {response}')


# Parsing json
def parse_olap_response(olap):
    json_olap = json.loads(olap)
    data_json = json_olap['data']
    later_orders = list()

    count_all_KITCHEN1 = 0
    count_all_KITCHEN2 = 0
    count_later_KITCHEN1 = 0
    count_later_KITCHEN2 = 0
    report_kitchen1 = ''
    report_kitchen2 = ''

    for i in range(len(data_json)):
        if data_json[i]['Department.Id'] == KITCHEN1:
            count_all_KITCHEN1 += 1
        elif data_json[i]['Department.Id'] == KITCHEN2:
            count_all_KITCHEN2 += 1
        else:
            pass

        t1 = data_json[i]['Delivery.ActualTime']
        t2 = data_json[i]['Delivery.PrintTime']
        # delete microseconds
        if len(t1) != 19:
            t1 = t1[:19]
        if len(t2) != 19:
            t2 = t2[:19]
        actual_time = datetime.strptime(t1, '%Y-%m-%dT%H:%M:%S')
        print_time = datetime.strptime(t2, '%Y-%m-%dT%H:%M:%S')
        max_delivery_time = timedelta(minutes=LATENESS_THRESHOLD_IN_MINUTES)
        delay = actual_time - print_time
        # print(delay, type(delay))
        if delay > max_delivery_time:
            order = Order(data_json[i])
            if data_json[i]['Department.Id'] == KITCHEN1:
                count_later_KITCHEN1 += 1
                report_kitchen1 += f'<strong>№ {order.number}</strong> ⏱ {delay.seconds//60} мин. 📞 {order.phone} 👤 {order.username}\n'
            else:
                count_later_KITCHEN2 += 1
                report_kitchen2 += f'<strong>№ {order.number}</strong> ⏱ {delay.seconds//60} мин. 📞 {order.phone} 👤 {order.username}\n'

            later_orders.append(order)

    text = f'<strong>🍕 Всего заказов: {count_all_KITCHEN1+count_all_KITCHEN2}</strong>\n<strong>' + \
        f'😡 Всего опозданий: {count_later_KITCHEN1+count_later_KITCHEN2}</strong>\n'
    text += f'Опоздание = ActualTime - PrintTime > {LATENESS_THRESHOLD_IN_MINUTES} мин.\n\n'
    text += '<strong>🏡 Кухня на Ленина</strong>\n' + f'Заказов: {count_all_KITCHEN2}\n' + \
        f'Опозданий: {count_later_KITCHEN2}\n' + report_kitchen2 + '\n'
    text2 = '<strong>🏡 Кухня на Лермонтова</strong>\n' + f'Заказов: {count_all_KITCHEN1}\n' + \
        f'Опозданий: {count_later_KITCHEN1}\n{report_kitchen1}\n'

    return later_orders, text, text2


def send_sms(later_orders):
    # Get token_ for_sms
    sms_token = str()
    url = f'{IP}:{PORT}/api/0/auth/access_token?user_id={IIKO_BIZ_LOGIN}&user_secret={IIKO_BIZ_PASSWORD}'
    code, response = process_send_request(url)
    if code == 200:
        sms_token = response[1:-1]
    else:
        send_error(f'Can\'t get sms_token. Code {code}. Response - {response}')

    for order in later_orders:
        # Get customer_id
        # order.phone = "XXXXXXXXXXX"    # for  test
        url = f'{IP}:{PORT}/api/0/customers/get_customer_by_phone?organization={ORGANIZATION_ID}&'\
            f'phone={order.phone}&access_token={sms_token}'
        code, response = process_send_request(url)
        if code == 200:
            response = json.loads(response)
            order.customerid = response['id']
            print(order.customerid)
        else:
            send_error(f'Can\'t get cutomerid. Code {code}. Response - {response}')

        # Add category
        url = f'{IP}:{PORT}/api/0/customers/{order.customerid}/remove_category?organization={ORGANIZATION_ID}\&'\
            f'categoryId=c8726b30-742b-48a4-af8b-1f4d03a88b4d&access_token={sms_token}'  # categoryId - later order
        code, response = process_send_request(url, method='POST')
        if code == 200:
            pass
        # elif code == 400:
        #     pass
        else:
            send_error(f'Can\'t add category for user {order.customerid}, phone - {order.phone}. Code {code}. Response - {response}')

        # Send sms
        url = f'{IP}:{PORT}/api/0/organization/{ORGANIZATION_ID}/send_sms?sendSmsRequest&access_token={sms_token}'
        payload = {'phoneNumber': order.phone, 'text': 'PROMOKOD'}
        code, response = process_send_request(url, method='POST', body=payload)
        if code == 200:
            pass
        else:
            send_error(f'Can\'t send sms for user {order.customerid}, phone - {order.phone}. Code {code}. Response - {response}')

        # break      # for test


def send_log_in_telegram(text):
    # separate on parts
    i = 0
    message_len = len(text)
    while i < message_len-1:
        index_strong = text[i:i+3900].rfind('<strong>')
        index_n = text[i:i+3900+200].find('\n', index_strong)
        url = f'https://api.telegram.org/bot{BOT_TOKEN}/sendMessage?chat_id={CHAT_ID}&text={text[i:i+index_n]}&parse_mode=HTML'
        # print(text[i:i+4096])
        r = requests.get(url)
        print(r.status_code)
        if r.status_code != 200:
            print(r.text)
            url = f'https://api.telegram.org/bot{BOT_TOKEN}/sendMessage?chat_id={CHAT_ID}&text={r.text}&parse_mode=HTML'
            r = requests.get(url)

        i += index_n+1


if __name__ == '__main__':
    try:
        ACCESS_TOKEN = get_access_token()
        olap = get_olap_data(ACCESS_TOKEN)

        later_orders, report_part1, report_part2 = parse_olap_response(olap)
        if not DEBUG:
            send_sms(later_orders)

        start = datetime.now(pytz.timezone(REPORT_TIMEZONE))
        day = (start - timedelta(days=1)).date().strftime('%d.%m.%Y')
        header = f'<strong>Отчет об опозданиях доставок за {day}</strong>\n'
        header += f"Сформирован {start.strftime('%d.%m.%Y')} в {start.strftime('%H:%M')} ({REPORT_TIMEZONE})\n\n"
        print('Отправляю в телеграм...')
        send_log_in_telegram(header + report_part1)
        send_log_in_telegram(report_part2)

    except Exception as E:
        print(E)
        send_log_in_telegram(f'Unexpected error: {E}')

    finally:
        release_license(ACCESS_TOKEN)
