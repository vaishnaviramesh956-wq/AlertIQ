import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import accuracy_score
import pickle

# Expanded training dataset (100 examples, balanced across 4 priorities)
data = {
    "message": [
        # Critical
        "Server is down, urgent fix needed",
        "Production database crashed immediately",
        "OTP for your transaction is 4521",
        "Your account has been compromised, act now",
        "Payment failed please retry immediately",
        "Security breach detected on your account",
        "Fraud alert unusual activity on your card",
        "Emergency meeting called right now",
        "Data breach detected please reset password",
        "Critical system failure in production",
        "Your OTP is 9034 do not share with anyone",
        "Website is down for all users urgent",
        "Unauthorized login attempt detected",
        "Bank alert suspicious transaction blocked",
        "Server crash affecting all customers now",
        "Urgent action required account suspended",
        "Database backup failed critical error",
        "Live outage impacting all services",
        "Your card has been blocked contact bank immediately",
        "Immediate response needed system down",
        "Critical deadline missed contact manager now",
        "Fire alarm triggered evacuate immediately",
        "Medical emergency please respond now",
        "Network outage affecting entire office",
        "Payment gateway down urgent escalation",
        # Important
        "Your salary has been credited to your account",
        "Tomorrow assignment submission deadline",
        "You have a new job match Software Engineer",
        "New job alert Python Developer at TCS",
        "Interview scheduled for tomorrow 10 AM",
        "Meeting rescheduled to 3 PM today",
        "Project deadline is this Friday",
        "Your document needs signature by tomorrow",
        "Contract renewal due next week",
        "Bill payment due in 2 days",
        "Appointment confirmed for tomorrow morning",
        "Your leave request has been approved",
        "Performance review scheduled next Monday",
        "Client call scheduled at 4 PM today",
        "Your flight is confirmed for tomorrow",
        "Exam results will be published tomorrow",
        "Your visa application status updated",
        "Team meeting agenda for this week",
        "Your subscription expires in 3 days",
        "Final reminder submit report by Friday",
        "Your loan application has been approved",
        "New assignment posted check portal",
        "Your package will be delivered tomorrow",
        "Password expires in 24 hours please update",
        "Your college fee payment deadline tomorrow",
        # Normal
        "Call me when free",
        "Happy birthday have a great day",
        "Sent you a message check when free",
        "How are you doing today",
        "Lunch plans for today",
        "Can we catch up this weekend",
        "Your order has been shipped",
        "Weather update rain expected today",
        "Reminder to water the plants",
        "Your package is out for delivery",
        "Group photo shared in album",
        "New episode released check it out",
        "Your table reservation is confirmed",
        "Thanks for the help yesterday",
        "Movie plan for tonight",
        "Your ride is arriving in 5 minutes",
        "Grocery list updated check app",
        "Your subscription renewed successfully",
        "New comment on your recent post",
        "Your favorite team won the match",
        "Daily step count summary available",
        "Your monthly bill is ready to view",
        "New recipe added to your saved list",
        "Your friend request was accepted",
        "Class notes shared in group",
        # Low
        "50 percent off sale today only",
        "New offers just for you",
        "John Doe liked your post",
        "Someone commented on your photo",
        "Flash sale ends in 2 hours",
        "Your profile viewed by 5 companies",
        "New followers this week check profile",
        "Weekly newsletter is here",
        "Limited time discount on electronics",
        "Someone shared your story",
        "New product launch check it out",
        "Your friend tagged you in a photo",
        "Clearance sale up to 70 percent off",
        "New trending video recommended for you",
        "Someone liked your comment",
        "Special coupon just for you today",
        "Your daily horoscope is ready",
        "New stickers pack available now",
        "Someone viewed your profile",
        "Weekend deals starting now",
        "New meme trending check it out",
        "Your app has a new update available",
        "Someone reacted to your status",
        "Buy one get one free offer today",
        "New playlist recommended for you",
    ],
    "priority": (
        ["Critical"] * 25 +
        ["Important"] * 25 +
        ["Normal"] * 25 +
        ["Low"] * 25
    )
}

df = pd.DataFrame(data)

vectorizer = TfidfVectorizer()
X_all = vectorizer.fit_transform(df["message"])

model = MultinomialNB()

# Cross-validation for a stable, reliable accuracy estimate
cv_scores = cross_val_score(model, X_all, df["priority"], cv=5)
print("Cross-validation accuracy (5-fold):", round(cv_scores.mean(), 4))
print("Individual fold scores:", [round(s, 2) for s in cv_scores])

# Train final model on ALL data for best real-world performance
model.fit(X_all, df["priority"])

# Save model and vectorizer
with open("model.pkl", "wb") as f:
    pickle.dump(model, f)

with open("vectorizer.pkl", "wb") as f:
    pickle.dump(vectorizer, f)

print("Model and vectorizer saved successfully!")