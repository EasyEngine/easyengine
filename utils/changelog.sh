#!/bin/bash

echo -e "## What's Changed\n" > changelog.txt

# Get the creation date of the most recent release
createdAt=$(gh api graphql -F owner='EasyEngine' -F name='easyengine' -f query='
  query {
    repository(owner: "EasyEngine", name: "easyengine") {
      releases(first: 1, orderBy: {field: CREATED_AT, direction: DESC}) {
        nodes { tagName, createdAt }
      }
    }
  }
' | jq -r '.data.repository.releases.nodes[0].createdAt')

# Also get the tag name for verification
tagName=$(gh api graphql -F owner='EasyEngine' -F name='easyengine' -f query='
  query {
    repository(owner: "EasyEngine", name: "easyengine") {
      releases(first: 1, orderBy: {field: CREATED_AT, direction: DESC}) {
        nodes { tagName, createdAt }
      }
    }
  }
' | jq -r '.data.repository.releases.nodes[0].tagName')

echo "Last release: $tagName ($createdAt)"

# Search for merged PRs since the last release with proper pagination
# Using merged:>$createdAt instead of updated:>$createdAt
gh api graphql --paginate -f query="
query(\$endCursor: String) {
  search(query: \"org:EasyEngine merged:>$createdAt is:pr is:merged\", type: ISSUE, first: 100, after: \$endCursor) {
    repositoryCount
    pageInfo {
      hasNextPage
      endCursor
    }
    edges {
      node {
        ... on PullRequest {
          title
          permalink
          state
          author {
            login
          }
          mergedAt
          repository {
            name
          }
        }
      }
    }
  }
}
" --template '{{range .data.search.edges}}{{"* "}}{{.node.title}} {{.node.permalink}}{{" @"}}{{.node.author.login}}{{"\n"}}{{end}}' >> changelog.txt
