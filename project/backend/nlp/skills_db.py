"""
Curated skills database.

We keep a hand-curated list rather than relying on pure NLP because:
  1. Skill extraction is a hard NER problem and small models miss a lot.
  2. A curated list with aliases gives reliable, predictable matches.
  3. Lab demos need consistent, explainable behavior.

Each entry: canonical skill name -> list of aliases (matched case-insensitively
with word boundaries). Aliases let us catch "JS" / "Javascript" / "java script".
"""

# ---------------------------------------------------------------------------
# Canonical skill -> aliases
# ---------------------------------------------------------------------------
SKILLS = {
    # --- Programming languages ----------------------------------------------
    "Python": ["python", "python3", "py"],
    "JavaScript": ["javascript", "js", "java script", "ecmascript"],
    "TypeScript": ["typescript", "ts"],
    "Java": ["java"],
    "C++": ["c++", "cpp", "cplusplus"],
    "C#": ["c#", "c sharp", "csharp"],
    "C": [" c ", "c language"],
    "Go": ["golang", " go "],
    "Rust": ["rust"],
    "Ruby": ["ruby"],
    "PHP": ["php"],
    "Swift": ["swift"],
    "Kotlin": ["kotlin"],
    "R": [" r ", "r language", "r programming"],
    "Scala": ["scala"],
    "MATLAB": ["matlab"],
    "Perl": ["perl"],
    "Dart": ["dart"],
    "SQL": ["sql"],
    "HTML": ["html", "html5"],
    "CSS": ["css", "css3"],
    "Bash": ["bash", "shell scripting", "shell script"],

    # --- Frontend frameworks ------------------------------------------------
    "React": ["react", "reactjs", "react.js"],
    "Vue.js": ["vue", "vuejs", "vue.js"],
    "Angular": ["angular", "angularjs", "angular.js"],
    "Svelte": ["svelte", "sveltekit"],
    "Next.js": ["next.js", "nextjs", "next js"],
    "Nuxt.js": ["nuxt", "nuxtjs", "nuxt.js"],
    "jQuery": ["jquery"],
    "Redux": ["redux"],
    "Tailwind CSS": ["tailwind", "tailwindcss", "tailwind css"],
    "Bootstrap": ["bootstrap"],
    "Sass": ["sass", "scss"],
    "Webpack": ["webpack"],
    "Vite": ["vite"],

    # --- Backend frameworks -------------------------------------------------
    "Node.js": ["node.js", "nodejs", "node js", " node "],
    "Express.js": ["express", "expressjs", "express.js"],
    "Django": ["django"],
    "Flask": ["flask"],
    "FastAPI": ["fastapi", "fast api"],
    "Spring Boot": ["spring boot", "springboot", "spring"],
    "Ruby on Rails": ["ruby on rails", "rails", "ror"],
    "Laravel": ["laravel"],
    ".NET": [".net", "dotnet", "asp.net"],
    "GraphQL": ["graphql"],
    "REST API": ["rest api", "restful", "rest"],
    "gRPC": ["grpc"],

    # --- Databases ----------------------------------------------------------
    "PostgreSQL": ["postgresql", "postgres"],
    "MySQL": ["mysql"],
    "MongoDB": ["mongodb", "mongo"],
    "Redis": ["redis"],
    "SQLite": ["sqlite"],
    "Oracle": ["oracle db", "oracle database"],
    "MS SQL Server": ["sql server", "mssql", "ms sql"],
    "Cassandra": ["cassandra"],
    "DynamoDB": ["dynamodb", "dynamo db"],
    "Elasticsearch": ["elasticsearch", "elastic search"],
    "Firebase": ["firebase"],
    "Supabase": ["supabase"],

    # --- Cloud & DevOps -----------------------------------------------------
    "AWS": ["aws", "amazon web services"],
    "Azure": ["azure", "microsoft azure"],
    "Google Cloud": ["gcp", "google cloud", "google cloud platform"],
    "Docker": ["docker"],
    "Kubernetes": ["kubernetes", "k8s"],
    "Terraform": ["terraform"],
    "Ansible": ["ansible"],
    "Jenkins": ["jenkins"],
    "GitHub Actions": ["github actions"],
    "GitLab CI": ["gitlab ci", "gitlab-ci"],
    "CircleCI": ["circleci", "circle ci"],
    "CI/CD": ["ci/cd", "cicd", "continuous integration"],
    "Linux": ["linux", "ubuntu", "debian", "centos"],
    "Nginx": ["nginx"],
    "Apache": ["apache"],

    # --- Data / ML / AI -----------------------------------------------------
    "Machine Learning": ["machine learning", "ml"],
    "Deep Learning": ["deep learning", "dl"],
    "TensorFlow": ["tensorflow", "tensor flow"],
    "PyTorch": ["pytorch", "torch"],
    "Keras": ["keras"],
    "Scikit-learn": ["scikit-learn", "sklearn", "scikit learn"],
    "Pandas": ["pandas"],
    "NumPy": ["numpy", "num py"],
    "Matplotlib": ["matplotlib"],
    "Seaborn": ["seaborn"],
    "OpenCV": ["opencv", "open cv"],
    "NLP": ["nlp", "natural language processing"],
    "Computer Vision": ["computer vision", " cv "],
    "Hugging Face": ["hugging face", "huggingface", "transformers"],
    "LangChain": ["langchain", "lang chain"],
    "spaCy": ["spacy"],
    "NLTK": ["nltk"],
    "LLM": ["llm", "large language model"],
    "Reinforcement Learning": ["reinforcement learning"],
    "Data Science": ["data science", "data scientist"],
    "Data Analysis": ["data analysis", "data analytics"],
    "Statistics": ["statistics", "statistical analysis"],
    "Tableau": ["tableau"],
    "Power BI": ["power bi", "powerbi"],
    "Apache Spark": ["spark", "apache spark", "pyspark"],
    "Hadoop": ["hadoop"],
    "Airflow": ["airflow", "apache airflow"],
    "Kafka": ["kafka", "apache kafka"],
    "ETL": ["etl", "extract transform load"],

    # --- Mobile -------------------------------------------------------------
    "React Native": ["react native"],
    "Flutter": ["flutter"],
    "iOS Development": ["ios development", "ios dev"],
    "Android Development": ["android development", "android dev"],
    "Xamarin": ["xamarin"],

    # --- Design & UI/UX -----------------------------------------------------
    "Figma": ["figma"],
    "Sketch": ["sketch"],
    "Adobe XD": ["adobe xd"],
    "Photoshop": ["photoshop", "adobe photoshop"],
    "Illustrator": ["illustrator", "adobe illustrator"],
    "UI/UX Design": ["ui/ux", "ui ux", "user experience", "user interface"],
    "Wireframing": ["wireframing", "wireframes"],
    "Prototyping": ["prototyping", "prototype"],

    # --- Tools / Version Control -------------------------------------------
    "Git": [" git ", "git "],
    "GitHub": ["github"],
    "GitLab": ["gitlab"],
    "Bitbucket": ["bitbucket"],
    "Jira": ["jira"],
    "Confluence": ["confluence"],
    "Slack": ["slack"],
    "Notion": ["notion"],
    "Trello": ["trello"],

    # --- Testing ------------------------------------------------------------
    "Jest": ["jest"],
    "Pytest": ["pytest"],
    "Selenium": ["selenium"],
    "Cypress": ["cypress"],
    "Unit Testing": ["unit testing", "unit tests"],
    "Integration Testing": ["integration testing"],
    "TDD": ["tdd", "test driven development"],

    # --- Methodologies & Soft Skills ---------------------------------------
    "Agile": ["agile"],
    "Scrum": ["scrum"],
    "Kanban": ["kanban"],
    "DevOps": ["devops"],
    "Microservices": ["microservices", "micro services"],
    "System Design": ["system design"],
    "Project Management": ["project management"],
    "Leadership": ["leadership", "team lead"],
    "Communication": ["communication skills"],
    "Problem Solving": ["problem solving", "problem-solving"],
    "Teamwork": ["teamwork", "team work"],
    "Critical Thinking": ["critical thinking"],

    # --- Security -----------------------------------------------------------
    "Cybersecurity": ["cybersecurity", "cyber security"],
    "Penetration Testing": ["penetration testing", "pen testing", "pentesting"],
    "OWASP": ["owasp"],
    "Cryptography": ["cryptography", "crypto"],

    # --- Blockchain ---------------------------------------------------------
    "Blockchain": ["blockchain"],
    "Solidity": ["solidity"],
    "Web3": ["web3", "web 3"],
    "Ethereum": ["ethereum"],
}


# ---------------------------------------------------------------------------
# Skill graph: related skills (used by BFS for "adjacent skills" recs).
# Edges are bidirectional and added automatically below.
# ---------------------------------------------------------------------------
_RAW_RELATIONS = [
    # Frontend cluster
    ("JavaScript", ["TypeScript", "React", "Vue.js", "Angular", "Node.js", "HTML", "CSS"]),
    ("TypeScript", ["JavaScript", "React", "Angular", "Node.js"]),
    ("React", ["Redux", "Next.js", "React Native", "TypeScript", "JavaScript"]),
    ("Vue.js", ["Nuxt.js", "JavaScript", "TypeScript"]),
    ("Angular", ["TypeScript", "RxJS", "JavaScript"]),
    ("Next.js", ["React", "Vercel", "TypeScript"]),
    ("HTML", ["CSS", "JavaScript", "Tailwind CSS"]),
    ("CSS", ["Sass", "Tailwind CSS", "Bootstrap", "HTML"]),
    ("Tailwind CSS", ["CSS", "React", "Next.js"]),

    # Backend cluster
    ("Node.js", ["Express.js", "JavaScript", "TypeScript", "MongoDB"]),
    ("Python", ["Django", "Flask", "FastAPI", "Pandas", "NumPy", "Machine Learning"]),
    ("Django", ["Python", "PostgreSQL", "REST API"]),
    ("Flask", ["Python", "REST API"]),
    ("FastAPI", ["Python", "REST API", "Pydantic"]),
    ("Java", ["Spring Boot", "Kotlin", "Maven"]),
    ("Spring Boot", ["Java", "Kotlin", "REST API"]),
    ("Ruby", ["Ruby on Rails"]),
    ("PHP", ["Laravel"]),

    # Database cluster
    ("SQL", ["PostgreSQL", "MySQL", "MS SQL Server", "SQLite"]),
    ("PostgreSQL", ["SQL", "Django"]),
    ("MongoDB", ["Node.js", "Express.js"]),
    ("Redis", ["PostgreSQL", "MongoDB"]),

    # DevOps cluster
    ("Docker", ["Kubernetes", "CI/CD", "Linux", "AWS"]),
    ("Kubernetes", ["Docker", "Helm", "AWS", "Terraform"]),
    ("AWS", ["Docker", "Kubernetes", "Terraform", "Linux"]),
    ("Azure", ["Docker", "Kubernetes", ".NET"]),
    ("Google Cloud", ["Kubernetes", "Docker", "Python"]),
    ("Terraform", ["AWS", "Azure", "Google Cloud", "Kubernetes"]),
    ("CI/CD", ["Jenkins", "GitHub Actions", "GitLab CI", "Docker"]),
    ("Jenkins", ["CI/CD", "Docker"]),
    ("Linux", ["Bash", "Docker", "Nginx"]),

    # ML / Data cluster
    ("Machine Learning", ["Deep Learning", "TensorFlow", "PyTorch",
                          "Scikit-learn", "Python", "Statistics"]),
    ("Deep Learning", ["TensorFlow", "PyTorch", "Keras", "Computer Vision", "NLP"]),
    ("TensorFlow", ["Keras", "Deep Learning", "Python"]),
    ("PyTorch", ["Deep Learning", "Python", "Hugging Face"]),
    ("Pandas", ["NumPy", "Python", "Data Analysis"]),
    ("NumPy", ["Pandas", "Python", "Matplotlib"]),
    ("Data Science", ["Pandas", "NumPy", "Scikit-learn", "Statistics", "Python", "R"]),
    ("Data Analysis", ["Pandas", "SQL", "Tableau", "Power BI", "Statistics"]),
    ("NLP", ["spaCy", "NLTK", "Hugging Face", "Deep Learning"]),
    ("Computer Vision", ["OpenCV", "PyTorch", "TensorFlow", "Deep Learning"]),

    # Mobile cluster
    ("React Native", ["React", "JavaScript", "iOS Development", "Android Development"]),
    ("Flutter", ["Dart", "iOS Development", "Android Development"]),
    ("Swift", ["iOS Development"]),
    ("Kotlin", ["Android Development", "Java"]),

    # Tools
    ("Git", ["GitHub", "GitLab", "Bitbucket"]),
    ("GitHub", ["Git", "GitHub Actions", "CI/CD"]),

    # Methodologies
    ("Agile", ["Scrum", "Kanban", "Jira"]),
    ("Scrum", ["Agile", "Jira"]),
    ("Microservices", ["Docker", "Kubernetes", "REST API", "gRPC"]),
]


def build_graph():
    """Return adjacency dict {skill: set(neighbors)}; bidirectional edges."""
    graph: dict[str, set[str]] = {}
    for src, neighbors in _RAW_RELATIONS:
        graph.setdefault(src, set()).update(neighbors)
        for n in neighbors:
            graph.setdefault(n, set()).add(src)
    return {k: sorted(v) for k, v in graph.items()}


SKILL_GRAPH = build_graph()


def all_skill_names() -> list[str]:
    """Return list of all canonical skill names."""
    return list(SKILLS.keys())


def all_aliases() -> dict[str, str]:
    """Return flat mapping alias_lower -> canonical_skill."""
    flat: dict[str, str] = {}
    for canonical, aliases in SKILLS.items():
        flat[canonical.lower()] = canonical
        for alias in aliases:
            flat[alias.lower().strip()] = canonical
    return flat
