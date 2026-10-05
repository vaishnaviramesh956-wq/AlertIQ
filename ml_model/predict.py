import sys
import os
import pickle

# Get the folder this script is in (so it works no matter who calls it)
script_dir = os.path.dirname(os.path.abspath(__file__))

# Load saved model and vectorizer using absolute paths
with open(os.path.join(script_dir, "model.pkl"), "rb") as f:
    model = pickle.load(f)

with open(os.path.join(script_dir, "vectorizer.pkl"), "rb") as f:
    vectorizer = pickle.load(f)

# Get message from command line argument
message = sys.argv[1]

# Predict
message_tfidf = vectorizer.transform([message])
prediction = model.predict(message_tfidf)[0]

print(prediction)