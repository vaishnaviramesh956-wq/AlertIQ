import os
import re
import sys
import json
import pickle
import mysql.connector
from google.auth.exceptions import RefreshError
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

# Windows terminals can choke on emoji / non-English characters in emails
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CREDENTIALS_FILE = os.path.join(BASE_DIR, "credentials.json")
TOKEN_FILE = os.path.join(BASE_DIR, "token.json")
PROCESSED_FILE = os.path.join(BASE_DIR, "processed_ids.json")
SCOPES = ["https://www.googleapis.com/auth/gmail.readonly"]
MAX_EMAILS = 10  # how many unread emails to check per run

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "alertiq",
}


def get_gmail_service():
    creds = None
    if os.path.exists(TOKEN_FILE):
        creds = Credentials.from_authorized_user_file(TOKEN_FILE, SCOPES)

    if creds and creds.expired and creds.refresh_token:
        try:
            creds.refresh(Request())
        except RefreshError:
            # Token expired/revoked (happens in Testing mode after ~7 days) -> log in again
            creds = None

    if not creds or not creds.valid:
        flow = InstalledAppFlow.from_client_secrets_file(CREDENTIALS_FILE, SCOPES)
        creds = flow.run_local_server(port=0)

    with open(TOKEN_FILE, "w") as f:
        f.write(creds.to_json())

    return build("gmail", "v1", credentials=creds)


def load_model():
    with open(os.path.join(BASE_DIR, "model.pkl"), "rb") as f:
        model = pickle.load(f)
    with open(os.path.join(BASE_DIR, "vectorizer.pkl"), "rb") as f:
        vectorizer = pickle.load(f)
    return model, vectorizer


def load_processed():
    if os.path.exists(PROCESSED_FILE):
        with open(PROCESSED_FILE, "r") as f:
            return set(json.load(f))
    return set()


def save_processed(ids):
    with open(PROCESSED_FILE, "w") as f:
        json.dump(sorted(ids), f)


def clean_sender(from_header):
    name = re.sub(r"<.*?>", "", from_header).strip().strip('"')
    return (name or from_header)[:100]


def main():
    if not os.path.exists(CREDENTIALS_FILE):
        print("credentials.json not found in the ml_model folder.")
        return

    model, vectorizer = load_model()
    service = get_gmail_service()
    processed = load_processed()

    results = service.users().messages().list(
        userId="me", q="is:unread in:inbox", maxResults=MAX_EMAILS
    ).execute()
    messages = [m for m in results.get("messages", []) if m["id"] not in processed]

    if not messages:
        print("No new unread emails to import.")
        return

    try:
        conn = mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as e:
        print(f"Could not connect to MySQL (is WAMP running?): {e}")
        return

    cursor = conn.cursor()
    added = 0

    for m in messages:
        msg = service.users().messages().get(
            userId="me", id=m["id"], format="metadata",
            metadataHeaders=["From", "Subject"],
        ).execute()

        headers = {h["name"]: h["value"] for h in msg.get("payload", {}).get("headers", [])}
        sender = clean_sender(headers.get("From", "Unknown"))
        subject = headers.get("Subject", "(no subject)")
        snippet = msg.get("snippet", "")

        text = f"{subject}. {snippet}".strip()
        priority = model.predict(vectorizer.transform([text]))[0]
        message = f"{subject} - {snippet}"[:250]

        cursor.execute(
            "INSERT INTO notifications (source, sender, message, priority, created_at) "
            "VALUES (%s, %s, %s, %s, NOW())",
            ("Gmail", sender, message, priority),
        )
        conn.commit()
        processed.add(m["id"])
        added += 1
        print(f"[{priority}] {sender}: {subject}")

    cursor.close()
    conn.close()
    save_processed(processed)
    print(f"\nDone. Imported {added} new email(s) into AlertIQ.")


if __name__ == "__main__":
    main()