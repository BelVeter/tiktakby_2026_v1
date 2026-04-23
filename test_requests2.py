import requests
import re

session = requests.Session()
# Get a zayavka ID
res = session.get('http://localhost/bb/rent_zayavk.php')
# Find order_id
match = re.search(r'name="order_id" value="(\d+)"', res.text)
if match:
    order_id = match.group(1)
    print("Found order_id:", order_id)
    # Attempt delete
    delete_data = {
        'action': 'удалить',
        'order_id': order_id,
        'user_id': '1'
    }
    res_del = session.post('http://localhost/bb/rent_zayavk.php', data=delete_data)
    print("Delete Status:", res_del.status_code)
    # Check if deleted
    res_check = session.get('http://localhost/bb/rent_zayavk.php')
    if order_id in res_check.text:
        print("Order still exists! Deletion failed.")
        with open("post_result.html", "w", encoding="utf-8") as f:
            f.write(res_del.text)
    else:
        print("Order deleted successfully via POST.")
else:
    print("No order found.")
