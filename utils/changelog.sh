#!/usr/bin/env bash

echo "## What's Changed" > changelog.txt

createdAt=$(gh api graphql -F owner='EasyEngine' -F name='easyengine' -f query='
  query {       
    repository(owner: "EasyEngine", name: "easyengine") {
      releases(last: 1) {
        nodes { tagName, createdAt }
      }
    }
  }
' | jq -r '.data.repository.releases.nodes[0].createdAt')
gh api graphql --paginate -f query="
query {
  search(query: \"org:Easyengine updated:>$createdAt state:closed is:pr\", type:ISSUE,first: 100) {
    repositoryCount
    edges {
      node {
        ... on PullRequest {
          title
          permalink
          state
          author {
            login
          }
          updatedAt
        }
      }
    }
  }
}
" --template '{{range .data.search.edges}}{{"* "}}{{.node.title}}{{" "}}{{.node.permalink}}{{" "}}{{.node.state}}{{" @"}}{{.node.author.login}}{{"\n"}}{{end}}' | sed '/CLOSED/d' | sed 's/ MERGED//g' >> changelog.txt
