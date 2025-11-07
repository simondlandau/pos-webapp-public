#!/bin/bash
# -------------------------------------------------------------------
# update_repo.sh
# Safely sync local svp_public directory with GitHub repo (pos-webapp-public)
# -------------------------------------------------------------------

set -e  # Stop on first error

echo "🔄 Updating local and remote repositories..."

# Ensure we're in the right folder
cd /var/www/finance/svp_public

# 1️⃣ Pull latest from GitHub to avoid conflicts
echo "📥 Pulling latest changes from GitHub..."
git pull origin main --no-rebase --autostash

# 2️⃣ Show current git status
echo ""
echo "📊 Current status:"
git status --short

# 3️⃣ Check if logs directory exists and show its status
if [ -d "logs" ]; then
    echo ""
    echo "📁 Logs directory status:"
    git status logs/ --short || echo "   ⚠️  Logs directory is not tracked by git"
fi

# 4️⃣ Stage all local changes
echo ""
echo "➕ Staging all modified and new files..."
git add .

# 5️⃣ Check if there are staged changes
if git diff-index --quiet HEAD --; then
    echo "✅ No changes to commit."
    exit 0
fi

# 6️⃣ Show what will be committed
echo ""
echo "📋 Files to be committed:"
git diff --cached --name-status

# 7️⃣ Ask for a commit message
echo ""
echo "📝 Enter a short description of your changes:"
read commit_message

# Handle empty commit message
if [ -z "$commit_message" ]; then
    commit_message="Update SVP files - $(date '+%Y-%m-%d %H:%M')"
    echo "   Using default message: $commit_message"
fi

# 8️⃣ Commit changes
git commit -m "$commit_message"

# 9️⃣ Push to GitHub
echo ""
echo "🚀 Pushing changes to GitHub..."
git push origin main

echo ""
echo "✅ SVP Repository successfully updated!"
echo ""

# 🔟 Show final status
echo "📊 Final repository status:"
git log --oneline -1
git status --short
