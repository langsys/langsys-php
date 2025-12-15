# Langsys API Documentation
Version: 1.0.0

## Authentication

### bearerAuth
Type: http
Scheme: bearer
Description: Enter token in format (Bearer <token>)

### apiKey
Type: apiKey
Name: X-Authorization
In: header
Description: An ApiKey security string

## Endpoints

### API Key

#### GET /api-keys
Summary: List API Keys
Operation ID: `a8a7a0ea6a191301f2dd26d26d31e424`

Description: Get all API keys the authenticated user has access to. Optionally filter by organization_id or project_id. organization_id will be ignored if project_id is provided as well. User must be an admin to access the api keys of a project or organization. Results can be ordered by: name, description, type, active, last_used_at, created_at, updated_at, id.

Security Requirements:
- bearerAuth

Parameters:
- `organization_id` in query: Filter results by organization ID
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `project_id` in query: Filter results by project ID
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    Type: array
    Items: 
      # Schema: ApiKeyListResponse
      Type: object
      Properties:
        status:
          Type: boolean
          Description: Response status
          Example: true

        data:
          Type: array
          Items: 
            allOf:
              # Schema: ApiKey
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d"

                name:
                  Type: string
                  Example: "Langsys Production Api Key"

                description:
                  Type: string
                  Description: Description of the API key
                  Example: "This API key is used for production environment"

                type:
                  Type: enum
                  Enum: ["read", "write"]
                  Example: "write"

                active:
                  Type: boolean
                  Example: true

                created_at:
                  Type: integer
                  Description: Unix timestamp of when the api key was created.
                  Example: 1764988634

                updated_at:
                  Type: integer
                  Description: Unix timestamp of when the api key was last updated.
                  Example: 1764988634

                last_used_at:
                  Type: integer
                  Description: Unix timestamp of when the api key was last used.
                  Example: 1764988634

                project:
                  Type: App\Data\ProjectBasic
                  allOf:
                    # Schema: ProjectBasic
                    Type: object
                    Properties:
                      id:
                        Type: string
                        Example: "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be"

                      title:
                        Type: string
                        Example: "Comercado"


                  Description: Details of the associated project.

                organization:
                  Type: App\Data\OrganizationBasic
                  allOf:
                    # Schema: OrganizationBasic
                    Type: object
                    Properties:
                      id:
                        Type: string
                        Example: "ddd131a2-2776-4642-8cfc-3c35a2ed1469"

                      name:
                        Type: string
                        Description: The name of the organization
                        Example: "Langsys Organization"


                  Description: Details of the associated organization.



          Description: List of items



  Example Response:
```json
[
  {
    "status": true,
    "data": [
      {
        "id": "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d",
        "name": "Langsys Production Api Key",
        "description": "This API key is used for production environment",
        "type": "write",
        "active": true,
        "created_at": 1764988634,
        "updated_at": 1764988634,
        "last_used_at": 1764988634,
        "project": {
          "id": "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be",
          "title": "Comercado"
        },
        "organization": {
          "id": "ddd131a2-2776-4642-8cfc-3c35a2ed1469",
          "name": "Langsys Organization"
        }
      }
    ]
  }
]
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: Validation Error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api-keys/{apiKeyId}
Summary: Update API Key
Operation ID: `ea0ebd4e9d88f95fd149f4a292cfae25`

Description: Update the project assigned to an API key.

Security Requirements:
- bearerAuth

Parameters:
- `apiKeyId` in path (Required): Id of API Key
  Type: string
  Example: `"03b8eacb-197c-4b83-ab5a-4ba88820ada1"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ApiKeyUpdateRequest
  Type: object
  Properties:
    name:
      Type: string
      Description: The name of the API key.
      Example: "My API Key"

    description:
      Type: string
      Example: "This API key is used for production environment"

    type:
      Type: enum
      Enum: ["read", "write"]
      Description: The type of the API key.
      Example: "write"

    active:
      Type: boolean
      Description: Whether the API key is active.
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ApiKeyResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ApiKey
        # Schema: ApiKey
        Type: object
        Properties:
          id:
            Type: string
            Example: "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d"

          name:
            Type: string
            Example: "Langsys Production Api Key"

          description:
            Type: string
            Description: Description of the API key
            Example: "This API key is used for production environment"

          type:
            Type: enum
            Enum: ["read", "write"]
            Example: "write"

          active:
            Type: boolean
            Example: true

          created_at:
            Type: integer
            Description: Unix timestamp of when the api key was created.
            Example: 1764988634

          updated_at:
            Type: integer
            Description: Unix timestamp of when the api key was last updated.
            Example: 1764988634

          last_used_at:
            Type: integer
            Description: Unix timestamp of when the api key was last used.
            Example: 1764988634

          project:
            Type: App\Data\ProjectBasic
            allOf:
              # Schema: ProjectBasic
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be"

                title:
                  Type: string
                  Example: "Comercado"


            Description: Details of the associated project.

          organization:
            Type: App\Data\OrganizationBasic
            allOf:
              # Schema: OrganizationBasic
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "ddd131a2-2776-4642-8cfc-3c35a2ed1469"

                name:
                  Type: string
                  Description: The name of the organization
                  Example: "Langsys Organization"


            Description: Details of the associated organization.



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d",
    "name": "Langsys Production Api Key",
    "description": "This API key is used for production environment",
    "type": "write",
    "active": true,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "last_used_at": 1764988634,
    "project": {
      "id": "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be",
      "title": "Comercado"
    },
    "organization": {
      "id": "ddd131a2-2776-4642-8cfc-3c35a2ed1469",
      "name": "Langsys Organization"
    }
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: Validation Error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/api-keys
Summary: Create a new API Key
Operation ID: `b013bbb35fa7ea324771a93c12145b80`

Description: Create a new API key and associate it with a project.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ApiKeyCreateRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Description: The project ID to associate the API key with.
      Example: "9b91ddef-3fa5-4125-988b-cc76ba4c78cc"

    name (Required):
      Type: string
      Description: The name of the API key.
      Example: "My API Key"

    description:
      Type: string
      Example: "This API key is used for production environment"

    type (Required):
      Type: enum
      Enum: ["read", "write"]
      Default: "read"
      Description: The type of the API key.
      Example: "read"

    active (Required):
      Type: boolean
      Default: true
      Description: Whether the API key is active.
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ApiKeyCreatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ApiKeyCreated
        # Schema: ApiKeyCreated
        Type: object
        Properties:
          id:
            Type: string
            Example: "979d8e68-620b-4805-a946-c5dbf40d724e"

          name:
            Type: string
            Example: "Langsys Production Api Key"

          description:
            Type: string
            Description: Description of the API key
            Example: "This API key is used for production environment"

          type:
            Type: enum
            Enum: ["read", "write"]
            Example: "read"

          active:
            Type: boolean
            Example: true

          key:
            Type: string
            Description: The actual API key value
            Example: "l8hqXC29KVUJamjxTV2nRSwEh0PyYiucf3UCOUZ6elL54AcrHsbI4YAXFA59Gdf2"

          created_at:
            Type: integer
            Description: Unix timestamp of when the api key was created.
            Example: 1764988634

          updated_at:
            Type: integer
            Description: Unix timestamp of when the api key was last updated.
            Example: 1764988634

          last_used_at:
            Type: integer
            Description: Unix timestamp of when the api key was last used.
            Example: 1764988634

          project:
            Type: App\Data\ProjectBasic
            allOf:
              # Schema: ProjectBasic
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be"

                title:
                  Type: string
                  Example: "Comercado"


            Description: Details of the associated project.

          organization:
            Type: App\Data\OrganizationBasic
            allOf:
              # Schema: OrganizationBasic
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "ddd131a2-2776-4642-8cfc-3c35a2ed1469"

                name:
                  Type: string
                  Description: The name of the organization
                  Example: "Langsys Organization"


            Description: Details of the associated organization.



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "979d8e68-620b-4805-a946-c5dbf40d724e",
    "name": "Langsys Production Api Key",
    "description": "This API key is used for production environment",
    "type": "read",
    "active": true,
    "key": "l8hqXC29KVUJamjxTV2nRSwEh0PyYiucf3UCOUZ6elL54AcrHsbI4YAXFA59Gdf2",
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "last_used_at": 1764988634,
    "project": {
      "id": "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be",
      "title": "Comercado"
    },
    "organization": {
      "id": "ddd131a2-2776-4642-8cfc-3c35a2ed1469",
      "name": "Langsys Organization"
    }
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: Validation Error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/api-keys/{apiKeyId}
Summary: Delete API Key
Operation ID: `cf716e4c79b95b69f2cbd69dd047521d`

Description: Delete an api key.

Security Requirements:
- bearerAuth

Parameters:
- `apiKeyId` in path (Required): Id of API Key
  Type: string
  Example: `"03b8eacb-197c-4b83-ab5a-4ba88820ada1"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ApiKeyResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ApiKey
        # Schema: ApiKey
        Type: object
        Properties:
          id:
            Type: string
            Example: "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d"

          name:
            Type: string
            Example: "Langsys Production Api Key"

          description:
            Type: string
            Description: Description of the API key
            Example: "This API key is used for production environment"

          type:
            Type: enum
            Enum: ["read", "write"]
            Example: "write"

          active:
            Type: boolean
            Example: true

          created_at:
            Type: integer
            Description: Unix timestamp of when the api key was created.
            Example: 1764988634

          updated_at:
            Type: integer
            Description: Unix timestamp of when the api key was last updated.
            Example: 1764988634

          last_used_at:
            Type: integer
            Description: Unix timestamp of when the api key was last used.
            Example: 1764988634

          project:
            Type: App\Data\ProjectBasic
            allOf:
              # Schema: ProjectBasic
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be"

                title:
                  Type: string
                  Example: "Comercado"


            Description: Details of the associated project.

          organization:
            Type: App\Data\OrganizationBasic
            allOf:
              # Schema: OrganizationBasic
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "ddd131a2-2776-4642-8cfc-3c35a2ed1469"

                name:
                  Type: string
                  Description: The name of the organization
                  Example: "Langsys Organization"


            Description: Details of the associated organization.



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d",
    "name": "Langsys Production Api Key",
    "description": "This API key is used for production environment",
    "type": "write",
    "active": true,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "last_used_at": 1764988634,
    "project": {
      "id": "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be",
      "title": "Comercado"
    },
    "organization": {
      "id": "ddd131a2-2776-4642-8cfc-3c35a2ed1469",
      "name": "Langsys Organization"
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### API Key - Reports

#### GET /api/api-keys/{apiKeyId}/activity
Summary: Get activity history per day for an API key
Operation ID: `ce6b303261e24e34a0291d9eeebe6d3d`

Description: Get per day activity for an specific API key

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `apiKeyId` in path (Required): Id of API Key
  Type: string
  Example: `"03b8eacb-197c-4b83-ab5a-4ba88820ada1"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ActivityPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Activity
            Type: object
            Properties:
              date:
                Type: string
                Description: Log date.
                Example: "130"

              get_requests:
                Type: integer
                Description: Total number of get requests.
                Example: 130

              post_requests:
                Type: integer
                Description: Total number of post requests.
                Example: 130

              patch_requests:
                Type: integer
                Description: Total number of patch requests.
                Example: 130

              delete_requests:
                Type: integer
                Description: Total number of delete requests.
                Example: 130



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "date": "130",
      "get_requests": 130,
      "post_requests": 130,
      "patch_requests": 130,
      "delete_requests": 130
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/api-keys/{apiKeyId}/activity-summary
Summary: Get total activity history for an API key
Operation ID: `8ab392cf07573ac3721b3576dad9b67a`

Description: Get total activity history for an API key.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `apiKeyId` in path (Required): Id of API Key
  Type: string
  Example: `"03b8eacb-197c-4b83-ab5a-4ba88820ada1"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ActivityResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Activity
        # Schema: Activity
        Type: object
        Properties:
          date:
            Type: string
            Description: Log date.
            Example: "130"

          get_requests:
            Type: integer
            Description: Total number of get requests.
            Example: 130

          post_requests:
            Type: integer
            Description: Total number of post requests.
            Example: 130

          patch_requests:
            Type: integer
            Description: Total number of patch requests.
            Example: 130

          delete_requests:
            Type: integer
            Description: Total number of delete requests.
            Example: 130



  Example Response:
```json
{
  "status": true,
  "data": {
    "date": "130",
    "get_requests": 130,
    "post_requests": 130,
    "patch_requests": 130,
    "delete_requests": 130
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Auth

#### POST /api/login
Summary: Authenticate User
Operation ID: `44212a9096e4b09358281e9ec8a0701d`

Description: Login with username and password

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: LoginRequest
  Type: object
  Properties:
    email (Required):
      Type: string
      Description: Login email
      Example: "sadie.grimes@gmail.com"

    password (Required):
      Type: string
      Description: Login password
      Example: "nR6#Aq^#T<?kI."

    device_id (Required):
      Type: string
      Description: This represents the client application, and the same value will need to be sent as a an x-device-id header in all subsequent requests.
      Example: "postman-24ba95bf"

    remember_me:
      Type: boolean
      Default: false
      Description: If set to true token will be valid for one week
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserWithTokenResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserWithToken
        # Schema: UserWithToken
        Type: object
        Properties:
          id:
            Type: string
            Example: "3c07bcbe-006e-4f69-a998-5ca501a578c1"

          firstname:
            Type: string
            Example: "Margie"

          lastname:
            Type: string
            Example: "Berge"

          email:
            Type: string
            Example: "wilkinson.elinore@cremin.com"

          phone:
            Type: string
            Example: "+1.804.635.8863"

          locale:
            Type: string
            Description: Base locale
            Example: "en-us"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          token:
            Type: string
            Example: "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4"

          token_type:
            Type: string
            Example: "Bearer"

          expires_at:
            Type: integer
            Example: 1764988634

          email_verified_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "3c07bcbe-006e-4f69-a998-5ca501a578c1",
    "firstname": "Margie",
    "lastname": "Berge",
    "email": "wilkinson.elinore@cremin.com",
    "phone": "+1.804.635.8863",
    "locale": "en-us",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "token": "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4",
    "token_type": "Bearer",
    "expires_at": 1764988634,
    "email_verified_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/register
Summary: Register New User
Operation ID: `a718f172ff3ac464d723835815f8fb57`

Description: Register a new user. If user is of type 'organization_owner' it will create an organization and add the user as its owner. Otherwise if user is of type 'translator' it will only create a user

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: RegisterRequest
  Type: object
  Properties:
    firstname (Required):
      Type: string
      Example: "Leonora"

    lastname (Required):
      Type: string
      Example: "Swaniawski"

    email (Required):
      Type: string
      Example: "bryce.simonis@hotmail.com"

    password (Required):
      Type: string
      Example: "K4x5mnscP&555"

    password_confirmation (Required):
      Type: string
      Example: "K4x5mnscP&555"

    organization:
      Type: App\Data\OrganizationData
      allOf:
        # Schema: OrganizationData
        Type: object
        Properties:
          name:
            Type: string
            Example: "My Organization"

          email:
            Type: string
            Example: "ffritsch@koepp.com"

          website_url:
            Type: string
            Example: "https://www.example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\OrganizationSettingsData
            allOf:
              # Schema: OrganizationSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Organization wide setting that determines the default machine translator to use in the projects.
                  Example: "deepl"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly usage limit for the organization.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                  Default: "account_balance_or_credit_card"
                  Description: Organization wide setting that determines the source of the automatic recharge.
                  Example: "organization_owner_balance"

                allow_draw_projects:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                  Example: true

                draw_projects_limit_monthly:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                  Example: 20



          address:
            Type: App\Data\Address
            allOf:
              # Schema: Address
              Type: object
              Properties:
                address_1:
                  Type: string
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Example: "Escazú"

                state:
                  Type: string
                  Example: "San José"

                zip:
                  Type: string
                  Example: "10203"

                country_code:
                  Type: string
                  Example: "CR"

                country:
                  Type: string
                  Example: "Costa Rica"





    plan_type (Required):
      Type: enum
      Enum: ["free", "business", "enterprise"]
      Default: "free"
      Example: "enterprise"

    plan_cycle (Required):
      Type: enum
      Enum: ["monthly", "yearly", "lifetime"]
      Default: "monthly"
      Example: "lifetime"

    credit_card:
      Type: App\Data\CreditCardData
      allOf:
        # Schema: CreditCardData
        Type: object
        Properties:
          cc_number:
            Type: string
            Description: Full credit card number.
            Example: "4111111111111111"

          cc_month:
            Type: string
            Description: Card expiration month (2 digits).
            Example: "02"

          cc_year:
            Type: string
            Description: Card expiration year (4 digits).
            Example: "2025"

          cc_name:
            Type: string
            Description: Cardholder name as it appears on the card.
            Example: "John Doe"

          cc_cvv:
            Type: string
            Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
            Example: "123"

          country_code:
            Type: string
            Description: Two-letter ISO country code where the card was issued or the billing address is located.
            Example: "US"

          address_1:
            Type: string
            Description: Address for the card.
            Example: "123 Main St"

          address_2:
            Type: string
            Description: Additional address line for the card.
            Example: "Apt 4B"

          city:
            Type: string
            Description: City for the card.
            Example: "San Francisco"

          state:
            Type: string
            Description: State/province for the card.
            Example: "CA"

          zip:
            Type: string
            Description: ZIP/postal code for the card.
            Example: "94105"



    billing_address:
      Type: App\Data\BillingAddressData
      allOf:
        # Schema: BillingAddressData
        Type: object
        Properties:
          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



    use_billing_address_for_payment (Required):
      Type: boolean
      Default: false
      Description: If true and credit card is provided, the billing address will be used as the payment method address
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserExtendedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserExtended
        # Schema: UserExtended
        Type: object
        Properties:
          id:
            Type: string
            Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

          firstname:
            Type: string
            Example: "Estelle"

          lastname:
            Type: string
            Example: "McLaughlin"

          email:
            Type: string
            Example: "schuppe.elmore@gmail.com"

          phone:
            Type: string
            Example: "(630) 622-5121"

          locale:
            Type: string
            Example: "es-cr"

          last_seen_at:
            Type: integer
            Description: Unix timestamp indicating last time the user interacted with the system.
            Example: 1764988634

          created_at:
            Type: integer
            Description: Unix timestamp indicating creation date.
            Example: 1764988634

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"


            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

          source_locales:
            Type: array
            Items: 
              Type: string
              Example: "en_MH"

            Description: List of locales user can translate from

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "ps_AF"

            Description: List of locales user can translate to

          settings:
            Type: App\Data\UserSettingsData
            allOf:
              # Schema: UserSettingsData
              Type: object
              Properties:
                notifications:
                  Type: App\Data\UserNotificationSettings
                  allOf:
                    # Schema: UserNotificationSettings
                    Type: object
                    Properties:
                      new_phrase:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      invitation:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      added_to_entity:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


                  Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: The maximum amount that can be drawn from the monthly balance of the user.
                  Example: 100

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Whether auto recharge is enabled for the user
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
                  Example: 20

                allow_draw_organizations:
                  Type: boolean
                  Default: true
                  Description: The allow draw organizations for the user
                  Example: true

                draw_organizations_limit_monthly:
                  Type: number
                  Format: float
                  Description: The draw organizations limit monthly for the user
                  Example: 100





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc",
    "firstname": "Estelle",
    "lastname": "McLaughlin",
    "email": "schuppe.elmore@gmail.com",
    "phone": "(630) 622-5121",
    "locale": "es-cr",
    "last_seen_at": 1764988634,
    "created_at": 1764988634,
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "source_locales": [
      "en_MH"
    ],
    "target_locales": [
      "ps_AF"
    ],
    "settings": {
      "notifications": {
        "new_phrase": [
          "broadcast"
        ],
        "invitation": [
          "broadcast"
        ],
        "added_to_entity": [
          "broadcast"
        ]
      },
      "monthly_credit_usage_limit": 100,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "allow_draw_organizations": true,
      "draw_organizations_limit_monthly": 100
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/register/{activationToken}
Summary: Register User with Invitation Token
Operation ID: `335a280460e8c2bbcdd0f67a77c335e6`

Description: Register a new user with an activation token. Registering with an activation token implies that this user will be added to an organization or project after creation.

Parameters:
- `activationToken` in path (Required): Activation token received by user through email.
  Type: string
  Example: `"CLplHEAqx1pjJyCxmhylgSqF2cxkMoFDIqCWvTgEMr6QJI0xop8goPONACyi"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: RegisterRequest
  Type: object
  Properties:
    firstname (Required):
      Type: string
      Example: "Leonora"

    lastname (Required):
      Type: string
      Example: "Swaniawski"

    email (Required):
      Type: string
      Example: "bryce.simonis@hotmail.com"

    password (Required):
      Type: string
      Example: "K4x5mnscP&555"

    password_confirmation (Required):
      Type: string
      Example: "K4x5mnscP&555"

    organization:
      Type: App\Data\OrganizationData
      allOf:
        # Schema: OrganizationData
        Type: object
        Properties:
          name:
            Type: string
            Example: "My Organization"

          email:
            Type: string
            Example: "ffritsch@koepp.com"

          website_url:
            Type: string
            Example: "https://www.example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\OrganizationSettingsData
            allOf:
              # Schema: OrganizationSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Organization wide setting that determines the default machine translator to use in the projects.
                  Example: "deepl"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly usage limit for the organization.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                  Default: "account_balance_or_credit_card"
                  Description: Organization wide setting that determines the source of the automatic recharge.
                  Example: "organization_owner_balance"

                allow_draw_projects:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                  Example: true

                draw_projects_limit_monthly:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                  Example: 20



          address:
            Type: App\Data\Address
            allOf:
              # Schema: Address
              Type: object
              Properties:
                address_1:
                  Type: string
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Example: "Escazú"

                state:
                  Type: string
                  Example: "San José"

                zip:
                  Type: string
                  Example: "10203"

                country_code:
                  Type: string
                  Example: "CR"

                country:
                  Type: string
                  Example: "Costa Rica"





    plan_type (Required):
      Type: enum
      Enum: ["free", "business", "enterprise"]
      Default: "free"
      Example: "enterprise"

    plan_cycle (Required):
      Type: enum
      Enum: ["monthly", "yearly", "lifetime"]
      Default: "monthly"
      Example: "lifetime"

    credit_card:
      Type: App\Data\CreditCardData
      allOf:
        # Schema: CreditCardData
        Type: object
        Properties:
          cc_number:
            Type: string
            Description: Full credit card number.
            Example: "4111111111111111"

          cc_month:
            Type: string
            Description: Card expiration month (2 digits).
            Example: "02"

          cc_year:
            Type: string
            Description: Card expiration year (4 digits).
            Example: "2025"

          cc_name:
            Type: string
            Description: Cardholder name as it appears on the card.
            Example: "John Doe"

          cc_cvv:
            Type: string
            Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
            Example: "123"

          country_code:
            Type: string
            Description: Two-letter ISO country code where the card was issued or the billing address is located.
            Example: "US"

          address_1:
            Type: string
            Description: Address for the card.
            Example: "123 Main St"

          address_2:
            Type: string
            Description: Additional address line for the card.
            Example: "Apt 4B"

          city:
            Type: string
            Description: City for the card.
            Example: "San Francisco"

          state:
            Type: string
            Description: State/province for the card.
            Example: "CA"

          zip:
            Type: string
            Description: ZIP/postal code for the card.
            Example: "94105"



    billing_address:
      Type: App\Data\BillingAddressData
      allOf:
        # Schema: BillingAddressData
        Type: object
        Properties:
          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



    use_billing_address_for_payment (Required):
      Type: boolean
      Default: false
      Description: If true and credit card is provided, the billing address will be used as the payment method address
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserWithTokenResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserWithToken
        # Schema: UserWithToken
        Type: object
        Properties:
          id:
            Type: string
            Example: "3c07bcbe-006e-4f69-a998-5ca501a578c1"

          firstname:
            Type: string
            Example: "Margie"

          lastname:
            Type: string
            Example: "Berge"

          email:
            Type: string
            Example: "wilkinson.elinore@cremin.com"

          phone:
            Type: string
            Example: "+1.804.635.8863"

          locale:
            Type: string
            Description: Base locale
            Example: "en-us"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          token:
            Type: string
            Example: "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4"

          token_type:
            Type: string
            Example: "Bearer"

          expires_at:
            Type: integer
            Example: 1764988634

          email_verified_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "3c07bcbe-006e-4f69-a998-5ca501a578c1",
    "firstname": "Margie",
    "lastname": "Berge",
    "email": "wilkinson.elinore@cremin.com",
    "phone": "+1.804.635.8863",
    "locale": "en-us",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "token": "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4",
    "token_type": "Bearer",
    "expires_at": 1764988634,
    "email_verified_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/activate/{activationToken}
Summary: Activate User Account
Operation ID: `f29bb8facfdbd43b0eb36c3b97a1d20f`

Description: If token is valid, account will be activated and user will be logged in. A bearer token will be available in the response.

Parameters:
- `activationToken` in path (Required): Activation token received by user through email.
  Type: string
  Example: `"CLplHEAqx1pjJyCxmhylgSqF2cxkMoFDIqCWvTgEMr6QJI0xop8goPONACyi"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserWithTokenResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserWithToken
        # Schema: UserWithToken
        Type: object
        Properties:
          id:
            Type: string
            Example: "3c07bcbe-006e-4f69-a998-5ca501a578c1"

          firstname:
            Type: string
            Example: "Margie"

          lastname:
            Type: string
            Example: "Berge"

          email:
            Type: string
            Example: "wilkinson.elinore@cremin.com"

          phone:
            Type: string
            Example: "+1.804.635.8863"

          locale:
            Type: string
            Description: Base locale
            Example: "en-us"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          token:
            Type: string
            Example: "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4"

          token_type:
            Type: string
            Example: "Bearer"

          expires_at:
            Type: integer
            Example: 1764988634

          email_verified_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "3c07bcbe-006e-4f69-a998-5ca501a578c1",
    "firstname": "Margie",
    "lastname": "Berge",
    "email": "wilkinson.elinore@cremin.com",
    "phone": "+1.804.635.8863",
    "locale": "en-us",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "token": "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4",
    "token_type": "Bearer",
    "expires_at": 1764988634,
    "email_verified_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/activate-email/{activationToken}
Summary: Verify New Email Address
Operation ID: `b19e771026491d5f0c887dc74072e1ce`

Description: If token is valid, the new email will be updated in the users table and deleted from the user_emails table.

Parameters:
- `activationToken` in path (Required): Activation token received by user through email.
  Type: string
  Example: `"CLplHEAqx1pjJyCxmhylgSqF2cxkMoFDIqCWvTgEMr6QJI0xop8goPONACyi"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/authorize-project/{projectId}
Summary: Validate API Key for Project
Operation ID: `db2e59448b7cc364029c0b2fa5cf35a9`

Description: Check if api key is valid for project. Api key should be sent as a X-Authorization header.

Security Requirements:
- apiKey

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ApiKeyProjectResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ApiKeyProject
        # Schema: ApiKeyProject
        Type: object
        Properties:
          id:
            Type: string
            Example: "980209f5-b8ba-4b67-b96a-c47cdc7a8e97"

          title:
            Type: string
            Example: "Comercado"

          base_locale:
            Type: string
            Description: Locale in which project phrase strings are written.
            Example: "en-us"

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "fr-ca"

            Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

          default_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

          key_type:
            Type: enum
            Enum: ["read", "write"]
            Description: Type of API key
            Example: "write"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "980209f5-b8ba-4b67-b96a-c47cdc7a8e97",
    "title": "Comercado",
    "base_locale": "en-us",
    "target_locales": [
      "fr-ca"
    ],
    "default_locales": [
      "es-cr"
    ],
    "key_type": "write"
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Utilities

#### GET /api/countries/{userLocale}
Summary: Get List of Countries in Specified Language
Operation ID: `268f63d611dbdbb6ac3191765ebdcdc2`

Description: Get a complete list of all countries with the country name in {localeCode} as label attribute. Pagination parameters are optional. Results can be ordered by: label, code, created_at, updated_at, id.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `userLocale` in path (Required): URL paramater representing locale code to display the data in.
  Type: string
  Example: `"en-us"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: CountryPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Country
            Type: object
            Properties:
              label:
                Type: string
                Description: Country name
                Example: "Costa Rica"

              code:
                Type: string
                Description: Country code
                Example: "CR"



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "label": "Costa Rica",
      "code": "CR"
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/countries/dial-codes/{userLocale}
Summary: Get List of Country Dial Codes in Specified Language
Operation ID: `f7c8af930df137f33ed2cee790a40dbf`

Description: Get a complete list of all countries dial codes with the country name in {userLocale} as name attribute. Pagination parameters are optional. Results can be ordered by: country_code, dial_code, name, created_at, updated_at, id.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `userLocale` in path (Required): URL paramater representing locale code to display the data in.
  Type: string
  Example: `"en-us"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: DialCodePaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: DialCode
            Type: object
            Properties:
              country_code:
                Type: string
                Description: Country Code
                Example: "CR"

              dial_code:
                Type: string
                Description: Dial code
                Example: "506"

              name:
                Type: string
                Description: The country name and the dial code
                Example: "Costa Rica (+506)"



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "country_code": "CR",
      "dial_code": "506",
      "name": "Costa Rica (+506)"
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/imagekit/auth
Summary: Generate ImageKit Authentication Token
Operation ID: `d2c99be899688412bff86de275072252`

Description: Get an image kit signature token.

Security Requirements:
- bearerAuth
- apiKey

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ImageKitResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ImageKit
        # Schema: ImageKit
        Type: object
        Properties:
          token:
            Type: string
            Example: "76b3e92c-96d7-4a24-a8d6-2e3cf048170c"

          expire:
            Type: integer
            Example: 1693025644

          signature:
            Type: string
            Example: "6466363c92c0cfc572c8c3d5f1bdeac2d52d827e"



  Example Response:
```json
{
  "status": true,
  "data": {
    "token": "76b3e92c-96d7-4a24-a8d6-2e3cf048170c",
    "expire": 1693025644,
    "signature": "6466363c92c0cfc572c8c3d5f1bdeac2d52d827e"
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/locales
Summary: Get a list of locales grouped by language
Operation ID: `95012f210cfbfb6894ebeb36c8fa637c`

Description: Get a list of locales grouped by language and displayed in the language of each requested locale.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `locales[]` in query: List of locales to display the data in. Each locale in this list will be used to group data displayed in each locale's language.
  Type: Array of string
  Example: `["en-us","es-cr","fr-fr"]`
- `project_id` in query: If provided, the project's target locales will be appended to the list of locales.
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `append_target_locales` in query: If project_id is not provided and this value is true, then the project's target locales will be appended to the list of locales. This is only when using api key authentication and project can be inferred from the api key
  Type: boolean
  Example: `"true"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: LocaleCategorizedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Response status

      data:
        Type: object
        Properties:
          en-us:
            Type: object
            Properties:
              Spanish:
                Type: array
                Items: 
                  Type: object
                  Properties:
                    code:
                      Type: string
                      Description: Locale code
                      Example: "es-cr"

                    name:
                      Type: string
                      Description: Locale name
                      Example: "Spanish (Costa Rica)"






  Example Response:
```json
{
  "status": true,
  "data": {
    "en-us": {
      "Spanish": [
        {
          "code": "es-cr",
          "name": "Spanish (Costa Rica)"
        }
      ]
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/locales/flat
Summary: Get flat list of locales
Operation ID: `413ba700c8a54fbba9187cba3691b016`

Description: Get a list of all locales.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `locales[]` in query: List of locales to display the data in. Each locale in this list will be used to group data displayed in each locale's language.
  Type: Array of string
  Example: `["en-us","es-cr","fr-fr"]`
- `project_id` in query: If provided, the project's target locales will be appended to the list of locales.
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `append_target_locales` in query: If project_id is not provided and this value is true, then the project's target locales will be appended to the list of locales. This is only when using api key authentication and project can be inferred from the api key
  Type: boolean
  Example: `"true"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ManualLocaleFlatResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Response status

      data:
        Type: object
        Properties:
          en-us:
            Type: array
            Items: 
              allOf:
                # Schema: LocaleFlat
                Type: object
                Properties:
                  code:
                    Type: string
                    Example: "es-cr"

                  name:
                    Type: string
                    Example: "Spanish (Costa Rica)"






  Example Response:
```json
{
  "status": true,
  "data": {
    "en-us": [
      {
        "code": "es-cr",
        "name": "Spanish (Costa Rica)"
      }
    ]
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/locales/data
Summary: Get detailed list of locales
Operation ID: `6d45c8b8691f51fb32b3b5ab6796129c`

Description: Get a list of all locales with their names and language names.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `locales[]` in query: List of locales to display the data in. Each locale in this list will be used to group data displayed in each locale's language.
  Type: Array of string
  Example: `["en-us","es-cr","fr-fr"]`
- `project_id` in query: If provided, the project's target locales will be appended to the list of locales.
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `append_target_locales` in query: If project_id is not provided and this value is true, then the project's target locales will be appended to the list of locales. This is only when using api key authentication and project can be inferred from the api key
  Type: boolean
  Example: `"true"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ManualLocaleDataResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Response status

      data:
        Type: object
        Properties:
          en-us:
            Type: array
            Items: 
              allOf:
                # Schema: Locale
                Type: object
                Properties:
                  code:
                    Type: string
                    Example: "es-cr"

                  locale_name:
                    Type: string
                    Example: "Spanish (Costa Rica)"

                  lang_name:
                    Type: string
                    Example: "Spanish"






  Example Response:
```json
{
  "status": true,
  "data": {
    "en-us": [
      {
        "code": "es-cr",
        "locale_name": "Spanish (Costa Rica)",
        "lang_name": "Spanish"
      }
    ]
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/plans
Summary: List subscription plans
Operation ID: `000824ea04c2b5f2039de937145a6035`

Description: Retrieve all the plans.

Security Requirements:
- bearerAuth

Responses:
- 200: Success
  Content-Type: `application/json`
  Schema:
    # Schema: PlanPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Plan
            Type: object
            Properties:
              id:
                Type: string
                Description: Plan ID.
                Example: "a3b8c9d0-1234-5678-9abc-def012345678"

              name:
                Type: string
                Description: Display name of the plan.
                Example: "Business"

              type:
                Type: enum
                Enum: ["free", "business", "enterprise"]
                Description: Type of the plan.
                Example: "enterprise"

              max_organizations:
                Type: integer
                Description: Maximum number of organizations allowed for this plan.
                Example: 3

              max_projects:
                Type: integer
                Description: Maximum number of projects allowed for this plan.
                Example: 10

              max_locales:
                Type: integer
                Description: Maximum number of locales allowed for this plan.
                Example: 5

              max_users:
                Type: integer
                Description: Maximum number of users allowed for this plan.
                Example: 25

              max_translator_users:
                Type: integer
                Description: Maximum number of translator users allowed for this plan.
                Example: 10

              price:
                Type: number
                Format: float
                Description: Monthly price for the plan. Null for Free and Enterprise plans.
                Example: 29



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "a3b8c9d0-1234-5678-9abc-def012345678",
      "name": "Business",
      "type": "enterprise",
      "max_organizations": 3,
      "max_projects": 10,
      "max_locales": 5,
      "max_users": 25,
      "max_translator_users": 10,
      "price": 29
    }
  ]
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Invitation

#### GET /api/invitations/{invitationId?}
Summary: Get User Invitation Details
Operation ID: `230a62df707fef6b3e288296caf51992`

Description: Get a single invitation. If invitation id is not provided, then invitation token must be provided. If invitation is retrieved by id, then only entity admins or invitees can access it.

Security Requirements:
- bearerAuth

Parameters:
- `invitationId?` in path: Optional Id of invitation
  Type: string
  Example: `"dc4c780d-0142-4683-ae32-052b11a7d222"`
- `token` in query: Invitation token. Must be provided if invitationId is not provided.
  Type: string
  Example: `"R95dHz0CwXZUlOynLEfZAcADWec7ZNDkUL3S8fqFeSLzcADM0igogokCMZtr"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Invitation
        # Schema: Invitation
        Type: object
        Properties:
          id:
            Type: string
            Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

          inviter_id:
            Type: string
            Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

          inviter:
            Type: string
            Example: "John Doe"

          invitee_id:
            Type: string
            Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

          invitee:
            Type: string
            Example: "John Miles"

          email:
            Type: string
            Example: "clement.terry@hotmail.com"

          entity_id:
            Type: string
            Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

          entity_type:
            Type: string
            Example: "Organization"

          entity_name:
            Type: string
            Example: "Flexmark"

          role:
            Type: string
            Example: "organization_admin"

          expires_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
    "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
    "inviter": "John Doe",
    "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
    "invitee": "John Miles",
    "email": "clement.terry@hotmail.com",
    "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
    "entity_type": "Organization",
    "entity_name": "Flexmark",
    "role": "organization_admin",
    "expires_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/invitations/{invitationId}/resend
Summary: Resend User Invitation
Operation ID: `67765fa6b3e1492029a0a3da9704ff33`

Description: Resend an invitation email to the user.

Security Requirements:
- bearerAuth

Parameters:
- `invitationId` in path (Required): Id of invitation
  Type: string
  Example: `"dc4c780d-0142-4683-ae32-052b11a7d222"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/invitations/accept/{activationToken}
Summary: Accept Invitation
Operation ID: `2394029755645d2d16effd428abf897d`

Description: Endpoint for users to accept an invitation via email link.

Security Requirements:
- bearerAuth

Parameters:
- `invitationToken` in path (Required): Activation token received by user through email.
  Type: string
  Example: `"X5jzBa9G36HMuR64x2TRf7Wy4Q15siq4Q6ZjWX2qaDztgxnxxnTrqwtMYzaV"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/invitations/decline/{activationToken}
Summary: Decline Invitation
Operation ID: `b4ffb394db0feb0c80108251b4044315`

Description: Endpoint for users to decline an invitation via email link.

Security Requirements:
- bearerAuth

Parameters:
- `invitationToken` in path (Required): Activation token received by user through email.
  Type: string
  Example: `"X5jzBa9G36HMuR64x2TRf7Wy4Q15siq4Q6ZjWX2qaDztgxnxxnTrqwtMYzaV"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/invitations/{invitationId}
Summary: Revoke Invitation
Operation ID: `78f68c5187fa3a1fa7152c8ae66fc780`

Description: Revoke a pending invitation. Only the invitation sender or entity owners can revoke invitations.

Security Requirements:
- bearerAuth

Parameters:
- `invitationId` in path (Required): Id of invitation
  Type: string
  Example: `"dc4c780d-0142-4683-ae32-052b11a7d222"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Invitation
        # Schema: Invitation
        Type: object
        Properties:
          id:
            Type: string
            Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

          inviter_id:
            Type: string
            Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

          inviter:
            Type: string
            Example: "John Doe"

          invitee_id:
            Type: string
            Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

          invitee:
            Type: string
            Example: "John Miles"

          email:
            Type: string
            Example: "clement.terry@hotmail.com"

          entity_id:
            Type: string
            Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

          entity_type:
            Type: string
            Example: "Organization"

          entity_name:
            Type: string
            Example: "Flexmark"

          role:
            Type: string
            Example: "organization_admin"

          expires_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
    "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
    "inviter": "John Doe",
    "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
    "invitee": "John Miles",
    "email": "clement.terry@hotmail.com",
    "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
    "entity_type": "Organization",
    "entity_name": "Flexmark",
    "role": "organization_admin",
    "expires_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Invoices

#### GET /api/invoices
Summary: Get all invoices for the authenticated user
Operation ID: `30b08eacc602146aef4bdd0dd6cc341e`

Description: Fetch all invoices for the currently authenticated user.

Security Requirements:
- bearerAuth

Parameters:
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvoiceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Invoice
        # Schema: Invoice
        Type: object
        Properties:
          id:
            Type: string
            Description: Invoice ID.
            Example: "6e917472-1237-4f5a-8cef-00a84125244f"

          file_name:
            Type: string
            Description: File name of the invoice.
            Example: "invoice_123456.pdf"

          type:
            Type: enum
            Enum: ["subscription", "prepaid_credits", "refund", "void"]
            Description: Invoice type.
            Example: "void"

          status:
            Type: string
            Description: Invoice status.
            Example: "paid"

          created_at:
            Type: string
            Description: Creation date of the invoice.
            Example: "2021-01-01 12:00:00"

          updated_at:
            Type: string
            Description: Last update date of the invoice.
            Example: "2021-01-05 14:30:00"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "6e917472-1237-4f5a-8cef-00a84125244f",
    "file_name": "invoice_123456.pdf",
    "type": "void",
    "status": "paid",
    "created_at": "2021-01-01 12:00:00",
    "updated_at": "2021-01-05 14:30:00"
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/invoices/{invoiceId}
Summary: Get a specific invoice for the authenticated user
Operation ID: `4b1a1751617e2ea0704ae524e233120e`

Description: Fetch a specific invoice for the currently authenticated user.

Security Requirements:
- bearerAuth

Parameters:
- `invoiceId` in path (Required): The ID of the invoice to fetch
  Type: string

Responses:
- 200: Success
  Content-Type: `application/json`
  Schema:
    # Schema: InvoiceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Invoice
        # Schema: Invoice
        Type: object
        Properties:
          id:
            Type: string
            Description: Invoice ID.
            Example: "6e917472-1237-4f5a-8cef-00a84125244f"

          file_name:
            Type: string
            Description: File name of the invoice.
            Example: "invoice_123456.pdf"

          type:
            Type: enum
            Enum: ["subscription", "prepaid_credits", "refund", "void"]
            Description: Invoice type.
            Example: "void"

          status:
            Type: string
            Description: Invoice status.
            Example: "paid"

          created_at:
            Type: string
            Description: Creation date of the invoice.
            Example: "2021-01-01 12:00:00"

          updated_at:
            Type: string
            Description: Last update date of the invoice.
            Example: "2021-01-05 14:30:00"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "6e917472-1237-4f5a-8cef-00a84125244f",
    "file_name": "invoice_123456.pdf",
    "type": "void",
    "status": "paid",
    "created_at": "2021-01-01 12:00:00",
    "updated_at": "2021-01-05 14:30:00"
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 404: Invoice not found
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Translations - Machine

#### GET /api/translations/machine/translators
Summary: List Available Translation Engines
Operation ID: `a6443821e2d89e3febb7e6f13719d18f`

Description: Get a list of all the machine translation engines along with the rate per million characters for each.

Security Requirements:
- bearerAuth
- apiKey

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: MachineTranslatorResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: MachineTranslator
        # Schema: MachineTranslator
        Type: object
        Properties:
          value:
            Type: string
            Description: Handle to use when selecting the service.
            Example: "chatgpt4o"

          label:
            Type: string
            Example: "ChatGPT 4o"

          translation_billing_rate:
            Type: number
            Format: float
            Description: Machine translation service rate per million characters.
            Example: 60

          language_detection_billing_rate:
            Type: number
            Format: float
            Description: Machine translation service rate per million characters.
            Example: 60



  Example Response:
```json
{
  "status": true,
  "data": {
    "value": "chatgpt4o",
    "label": "ChatGPT 4o",
    "translation_billing_rate": 60,
    "language_detection_billing_rate": 60
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translations/machine/translate
Summary: Machine Translate Translatable Items
Operation ID: `d1b64b37a6d5c6cadfd19f71a8614dfa`

Description: Machine translate a list of translatable items using the project's default machine translator or a custom one. For content blocks, all associated phrases will be translated. Optionally specify target locales; if not provided, all default target locales for the project will be used.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: MachineTranslateTranslatableItemsRequest
  Type: object
  Properties:
    translatable_item_ids (Required):
      Type: array
      Items: 
        Type: string
        Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

      Description: List of translatable item IDs to machine translate. For content blocks, all associated phrases will be translated. All translatable items must belong to the same project.

    machine_translator:
      Type: enum
      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
      Default: "default"
      Description: Custom machine translator to use. If not provided, machine translator in project settings will be used.
      Example: "default"

    locales:
      Type: array
      Items: 
        Type: string
        Example: "es-es"

      Description: List of target locales to translate to. If not provided, all project target locales will be used.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: MachineTranslateTranslatableItemsManualResponse
    Type: object
    allOf:
      # Schema: OK
      Type: object
      Properties:
        status:
          Type: boolean
          Default: true
          Description: Success


      Type: object
      Properties:
        data:
          Type: object
          Properties:
            es-cr:
              Type: array
              Items: 
                allOf:
                  # Schema: TranslatableItemTranslations
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "9c99afd7-38ec-42e0-97fd-da626eeff08a"

                    project_id:
                      Type: string
                      Example: "d951ca8f-8e6f-4d62-b47a-3de9000392dd"

                    label:
                      Type: string
                      Description: Sanitized phrase truncated to 25 chars.
                      Example: "About"

                    locale:
                      Type: string
                      Example: "es-es"

                    category:
                      Type: string
                      Description: Phrase or content block context category.
                      Example: "UI"

                    type:
                      Type: enum
                      Enum: ["phrase", "content_block"]
                      Example: "phrase"

                    phrase_id:
                      Type: string
                      Description: Phrase id. This field will be null if the request is for a content block.
                      Example: "e21c852c-99c0-42a7-be81-767716560693"

                    phrase:
                      Type: string
                      Description: Phrase text. This field will be null if the request is for a content block.
                      Example: "About"

                    translation_id:
                      Type: string
                      Description: This field will be null if the request is for a content block.
                      Example: "8c7d0ab7-b54c-428e-8ce8-42bce0caad08"

                    translation:
                      Type: string
                      Description: Translation text in the locale requested. This field will be null if the request is for a content block.
                      Example: "Nosotros"

                    translator:
                      Type: App\Http\Resources\UserSimpleResource
                      allOf:
                        # Schema: UserSimple
                        Type: object
                        Properties:
                          id:
                            Type: string
                            Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                          firstname:
                            Type: string
                            Example: "Laisha"

                          lastname:
                            Type: string
                            Example: "Eichmann"

                          avatar:
                            Type: App\Data\Avatar
                            allOf:
                              # Schema: Avatar
                              Type: object
                              Properties:
                                width:
                                  Type: integer
                                  Example: 481

                                height:
                                  Type: integer
                                  Example: 396

                                original_url:
                                  Type: string
                                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                                thumb_url:
                                  Type: string
                                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                                medium_url:
                                  Type: string
                                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                                id:
                                  Type: string
                                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                                path:
                                  Type: string
                                  Description: Path of local file
                                  Example: "/public/images"


                            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                      Description: User that translated the phrase in case it was translated by a human.

                    machine_translator:
                      Type: enum
                      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                      Default: "default"
                      Description: Machine translator used to translate the phrase.
                      Example: "xai"

                    content_block_id:
                      Type: string
                      Description: This field will be null if the translatable item is a phrase.
                      Example: "6c2dce67-b078-40c2-9111-ee9dae1c686b"

                    custom_id:
                      Type: string
                      Description: Custom id for content block. This field will be null if the translatable item is a phrase.
                      Example: "blE14pfd1$"

                    content:
                      Type: string
                      Description: Content block html content. This field will be null if the translatable item is a phrase.
                      Example: "<p>About <strong>us</strong></p>"

                    translations:
                      Type: array
                      Items: 
                        allOf:
                          # Schema: TranslationWithPhrase
                          Type: object
                          Properties:
                            id:
                              Type: string
                              Example: "9ced23bd-26af-4d80-82fc-533380c2f756"

                            translation_id:
                              Type: string
                              Example: "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7"

                            label:
                              Type: string
                              Description: Sanitized phrase truncated to 25 chars.
                              Example: "Home"

                            locale:
                              Type: string
                              Example: "es-es"

                            category:
                              Type: string
                              Description: Phrase context category.
                              Example: "UI"

                            phrase:
                              Type: string
                              Example: "Home"

                            phrase_id:
                              Type: string
                              Example: "170e9036-5bc6-4183-aa24-1813c8738d6e"

                            content_block_id:
                              Type: string
                              Example: "49666b64-7eb4-473e-9ab6-2a63b1febe43"

                            translation:
                              Type: string
                              Description: Translation text in the locale provided in this response.
                              Example: "Inicio"

                            untranslated:
                              Type: boolean
                              Example: true

                            translatable:
                              Type: boolean
                              Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                              Example: true

                            restorable:
                              Type: boolean
                              Description: Whether this phrase is able to be restored after being marked as untranslatable.
                              Example: false

                            human_translated:
                              Type: boolean
                              Description: Whether translation was done by a human.
                              Example: true

                            memory_translated:
                              Type: boolean
                              Description: Whether translation comes from translation memory.
                              Example: true

                            ai_translated:
                              Type: boolean
                              Description: Whether translation is translated by AI.
                              Example: false

                            translator:
                              Type: App\Http\Resources\UserSimpleResource
                              allOf:
                                # Schema: UserSimple
                                Type: object
                                Properties:
                                  id:
                                    Type: string
                                    Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                                  firstname:
                                    Type: string
                                    Example: "Laisha"

                                  lastname:
                                    Type: string
                                    Example: "Eichmann"

                                  avatar:
                                    Type: App\Data\Avatar
                                    allOf:
                                      # Schema: Avatar
                                      Type: object
                                      Properties:
                                        width:
                                          Type: integer
                                          Example: 481

                                        height:
                                          Type: integer
                                          Example: 396

                                        original_url:
                                          Type: string
                                          Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                                        thumb_url:
                                          Type: string
                                          Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                                        medium_url:
                                          Type: string
                                          Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                                        id:
                                          Type: string
                                          Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                                        path:
                                          Type: string
                                          Description: Path of local file
                                          Example: "/public/images"


                                    Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                              Description: User that translated the phrase.

                            machine_translator:
                              Type: enum
                              Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                              Description: Machine translator used to translate the phrase.
                              Example: "google"

                            words:
                              Type: integer
                              Example: 1

                            created_at:
                              Type: integer
                              Example: 1764988634

                            updated_at:
                              Type: integer
                              Example: 1764988634

                            deleted_at:
                              Type: integer
                              Example: 1764988634



                      Description: List of translations for content block. This field will be null if the request is for a single phrase.

                    words:
                      Type: integer
                      Default: 0
                      Example: 1

                    untranslated:
                      Type: boolean
                      Default: false
                      Example: true

                    translatable:
                      Type: boolean
                      Default: false
                      Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                      Example: true

                    restorable:
                      Type: boolean
                      Default: false
                      Description: Whether this phrase is able to be restored after being marked as untranslatable.
                      Example: false

                    human_translated:
                      Type: boolean
                      Default: false
                      Description: Whether translation was done by a human.
                      Example: true

                    memory_translated:
                      Type: boolean
                      Default: false
                      Description: Whether translation comes from translation memory.
                      Example: true

                    ai_translated:
                      Type: boolean
                      Default: false
                      Description: Whether translation is translated by AI.
                      Example: false

                    created_at:
                      Type: integer
                      Example: 1764988634

                    updated_at:
                      Type: integer
                      Example: 1764988634

                    deleted_at:
                      Type: integer
                      Example: 1764988634



              Description: Requested target locales




  Example Response:
```json
{
  "status": true,
  "data": {
    "es-cr": [
      {
        "id": "9c99afd7-38ec-42e0-97fd-da626eeff08a",
        "project_id": "d951ca8f-8e6f-4d62-b47a-3de9000392dd",
        "label": "About",
        "locale": "es-es",
        "category": "UI",
        "type": "phrase",
        "phrase_id": "e21c852c-99c0-42a7-be81-767716560693",
        "phrase": "About",
        "translation_id": "8c7d0ab7-b54c-428e-8ce8-42bce0caad08",
        "translation": "Nosotros",
        "translator": {
          "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
          "firstname": "Laisha",
          "lastname": "Eichmann",
          "avatar": {
            "width": 481,
            "height": 396,
            "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
            "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
            "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
            "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
            "path": "/public/images"
          }
        },
        "machine_translator": "xai",
        "content_block_id": "6c2dce67-b078-40c2-9111-ee9dae1c686b",
        "custom_id": "blE14pfd1$",
        "content": "<p>About <strong>us</strong></p>",
        "translations": [
          {
            "id": "9ced23bd-26af-4d80-82fc-533380c2f756",
            "translation_id": "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7",
            "label": "Home",
            "locale": "es-es",
            "category": "UI",
            "phrase": "Home",
            "phrase_id": "170e9036-5bc6-4183-aa24-1813c8738d6e",
            "content_block_id": "49666b64-7eb4-473e-9ab6-2a63b1febe43",
            "translation": "Inicio",
            "untranslated": true,
            "translatable": true,
            "restorable": false,
            "human_translated": true,
            "memory_translated": true,
            "ai_translated": false,
            "translator": {
              "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
              "firstname": "Laisha",
              "lastname": "Eichmann",
              "avatar": {
                "width": 481,
                "height": 396,
                "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
                "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
                "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
                "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
                "path": "/public/images"
              }
            },
            "machine_translator": "google",
            "words": 1,
            "created_at": 1764988634,
            "updated_at": 1764988634,
            "deleted_at": 1764988634
          }
        ],
        "words": 1,
        "untranslated": true,
        "translatable": true,
        "restorable": false,
        "human_translated": true,
        "memory_translated": true,
        "ai_translated": false,
        "created_at": 1764988634,
        "updated_at": 1764988634,
        "deleted_at": 1764988634
      }
    ]
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/translations/machine/{translatableItemId}
Summary: Get Machine Translation for Translatable Item
Operation ID: `36eb2356d18e61a5f8ea35362005b398`

Description: Get a machine translation for a translatable item and locale.

Security Requirements:
- bearerAuth

Parameters:
- `translatableItemId` in path (Required): Translatable item ID to delete
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `locale` in query (Required): No description
  Type: string
  Example: `"es-cr"`
- `machine_translator` in query: Machine translator to use for the translation. If not provided, machine translator in project settings will be used.
  Type: enum
  Example: `"chatgpt4o"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: MachineTranslationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: MachineTranslation
        # Schema: MachineTranslation
        Type: object
        Properties:
          translations:
            Type: array
            Items: 
              Type: string
              Example: "Texto traducido"

            Description: Array of translated strings

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Description: Machine translator used
            Example: "deepl"



  Example Response:
```json
{
  "status": true,
  "data": {
    "translations": [
      "Texto traducido"
    ],
    "machine_translator": "deepl"
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translations/machine/translate-untranslated
Summary: Machine Translate Untranslated Phrases
Operation ID: `366b60cca2bfa78ade142be1a296bf98`

Description: Machine translate all untranslated phrases in a project using machine translation service defined in project settings.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: MachineTranslateUntranslatedRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Example: "822091b3-66b0-42d2-8e17-8f59091de522"

    locale (Required):
      Type: string
      Description: Target locale for machine translation of untranslated phrases
      Example: "es-cr"

    machine_translator:
      Type: enum
      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
      Default: "default"
      Description: Custom machine translator to use. If not provided, machine translator in project settings will be used.
      Example: "google"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Notification

#### GET /api/notifications
Summary: Get User's Notifications
Operation ID: `0bb334cc4f9efd4fc1e6a0b32d8cd65d`

Description: Get all notifications the authenticated user has. Pagination parameters are optional. Results can be ordered by: message, action, read_at, created_at, updated_at, id.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: NotificationPaginatedResponseWithMeta
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      page_count:
        Type: integer
        Description: Total number of pages
        Example: 5

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      total_records:
        Type: integer
        Description: Total number of records
        Example: 40

      read:
        Type: integer
        Description: Number of read notifications
        Example: 4

      unread:
        Type: integer
        Description: Number of unread notifications
        Example: 3

      data:
        Type: array
        Items: 
          # Schema: Notification
          Type: object
          Properties:
            id:
              Type: string
              Example: "79d7e093-fa90-4f71-ae30-219f31abb761"

            message:
              Type: string
              Example: "8 new phrase(s) have been created in Project ABC"

            type:
              Type: enum
              Enum: ["invitation", "new_phrase", "added_to_entity"]
              Description: Type of notification. Should be used by client to decide how to display the notification.
              Example: "added_to_entity"

            data:
              Type: App\Data\NotificationPayloadData
              allOf:
                # Schema: NotificationPayloadData
                Type: object
                Properties:
                  entity_ids:
                    Type: array
                    Items: 
                      Type: string
                      Example: "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"



              Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

            created_at:
              Type: integer
              Example: 1764988634

            read_at:
              Type: integer
              Example: 1764988634


        Description: Array of notifications


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "page_count": 5,
  "records_per_page": 8,
  "total_records": 40,
  "read": 4,
  "unread": 3,
  "data": [
    {
      "id": "79d7e093-fa90-4f71-ae30-219f31abb761",
      "message": "8 new phrase(s) have been created in Project ABC",
      "type": "added_to_entity",
      "data": {
        "entity_ids": [
          "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"
        ]
      },
      "created_at": 1764988634,
      "read_at": 1764988634
    }
  ]
}
```

---

#### GET /api/notifications/{notificationId}
Summary: Get Single Notification
Operation ID: `20bed043f54583d51f73f9aafc0d684b`

Description: Get a single notification by its id. The notification must be owned by the authenticated user

Security Requirements:
- bearerAuth

Parameters:
- `notificationId` in path (Required): Id of notification
  Type: string
  Example: `"5de2b452-8ce0-4136-a5e6-849a1da1c385"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: NotificationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Notification
        # Schema: Notification
        Type: object
        Properties:
          id:
            Type: string
            Example: "79d7e093-fa90-4f71-ae30-219f31abb761"

          message:
            Type: string
            Example: "8 new phrase(s) have been created in Project ABC"

          type:
            Type: enum
            Enum: ["invitation", "new_phrase", "added_to_entity"]
            Description: Type of notification. Should be used by client to decide how to display the notification.
            Example: "added_to_entity"

          data:
            Type: App\Data\NotificationPayloadData
            allOf:
              # Schema: NotificationPayloadData
              Type: object
              Properties:
                entity_ids:
                  Type: array
                  Items: 
                    Type: string
                    Example: "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"



            Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

          created_at:
            Type: integer
            Example: 1764988634

          read_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "79d7e093-fa90-4f71-ae30-219f31abb761",
    "message": "8 new phrase(s) have been created in Project ABC",
    "type": "added_to_entity",
    "data": {
      "entity_ids": [
        "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"
      ]
    },
    "created_at": 1764988634,
    "read_at": 1764988634
  }
}
```
- 404: error
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/notifications/{notificationId}
Summary: Delete Notification
Operation ID: `c52f71fafb3ae4a48ddbc3f666741b9f`

Description: Delete a notification.

Security Requirements:
- bearerAuth

Parameters:
- `notificationId` in path (Required): Id of notification
  Type: string
  Example: `"5de2b452-8ce0-4136-a5e6-849a1da1c385"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: NotificationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Notification
        # Schema: Notification
        Type: object
        Properties:
          id:
            Type: string
            Example: "79d7e093-fa90-4f71-ae30-219f31abb761"

          message:
            Type: string
            Example: "8 new phrase(s) have been created in Project ABC"

          type:
            Type: enum
            Enum: ["invitation", "new_phrase", "added_to_entity"]
            Description: Type of notification. Should be used by client to decide how to display the notification.
            Example: "added_to_entity"

          data:
            Type: App\Data\NotificationPayloadData
            allOf:
              # Schema: NotificationPayloadData
              Type: object
              Properties:
                entity_ids:
                  Type: array
                  Items: 
                    Type: string
                    Example: "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"



            Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

          created_at:
            Type: integer
            Example: 1764988634

          read_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "79d7e093-fa90-4f71-ae30-219f31abb761",
    "message": "8 new phrase(s) have been created in Project ABC",
    "type": "added_to_entity",
    "data": {
      "entity_ids": [
        "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"
      ]
    },
    "created_at": 1764988634,
    "read_at": 1764988634
  }
}
```
- 404: error
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/notifications/read/{notificationId?}
Summary: Mark Notification(s) as Read
Operation ID: `2d996b77480a90edcefa77f3299b8861`

Description: Marks notification(s) as read. Can mark a single notification via path parameter or multiple notifications via request body. If both options are used, the id in the path will be included as part of the ids in the request body. All notifications must be owned by the authenticated user. If a notification is not owned by the authenticated user or an invalid id is provided, it will be ignored.

Security Requirements:
- bearerAuth

Parameters:
- `notificationId?` in path: Id of notification
  Type: string
  Example: `"5de2b452-8ce0-4136-a5e6-849a1da1c385"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: NotificationMarkStatusRequest
  Type: object
  Properties:
    notification_ids:
      Type: array
      Items: 
        Type: string
        Example: "18c2c202-556d-4a09-a3d7-e26750fe137d"

      Description: Array of notification IDs to mark as read or unread



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: NotificationPaginatedResponseWithMeta
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      page_count:
        Type: integer
        Description: Total number of pages
        Example: 5

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      total_records:
        Type: integer
        Description: Total number of records
        Example: 40

      read:
        Type: integer
        Description: Number of read notifications
        Example: 4

      unread:
        Type: integer
        Description: Number of unread notifications
        Example: 3

      data:
        Type: array
        Items: 
          # Schema: Notification
          Type: object
          Properties:
            id:
              Type: string
              Example: "79d7e093-fa90-4f71-ae30-219f31abb761"

            message:
              Type: string
              Example: "8 new phrase(s) have been created in Project ABC"

            type:
              Type: enum
              Enum: ["invitation", "new_phrase", "added_to_entity"]
              Description: Type of notification. Should be used by client to decide how to display the notification.
              Example: "added_to_entity"

            data:
              Type: App\Data\NotificationPayloadData
              allOf:
                # Schema: NotificationPayloadData
                Type: object
                Properties:
                  entity_ids:
                    Type: array
                    Items: 
                      Type: string
                      Example: "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"



              Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

            created_at:
              Type: integer
              Example: 1764988634

            read_at:
              Type: integer
              Example: 1764988634


        Description: Array of notifications


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "page_count": 5,
  "records_per_page": 8,
  "total_records": 40,
  "read": 4,
  "unread": 3,
  "data": [
    {
      "id": "79d7e093-fa90-4f71-ae30-219f31abb761",
      "message": "8 new phrase(s) have been created in Project ABC",
      "type": "added_to_entity",
      "data": {
        "entity_ids": [
          "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"
        ]
      },
      "created_at": 1764988634,
      "read_at": 1764988634
    }
  ]
}
```
- 404: error
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/notifications/unread/{notificationId?}
Summary: Mark Notification(s) as Unread
Operation ID: `a843e6a52eb34e02a301dd4af79eeb3d`

Description: Marks notification(s) as unread. Can mark a single notification via path parameter or multiple notifications via request body. If both options are used, the id in the path will be included as part of the ids in the request body. All notifications must be owned by the authenticated user. If a notification is not owned by the authenticated user or an invalid id is provided, it will be ignored.

Security Requirements:
- bearerAuth

Parameters:
- `notificationId?` in path: Id of notification
  Type: string
  Example: `"5de2b452-8ce0-4136-a5e6-849a1da1c385"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: NotificationMarkStatusRequest
  Type: object
  Properties:
    notification_ids:
      Type: array
      Items: 
        Type: string
        Example: "18c2c202-556d-4a09-a3d7-e26750fe137d"

      Description: Array of notification IDs to mark as read or unread



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: NotificationPaginatedResponseWithMeta
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      page_count:
        Type: integer
        Description: Total number of pages
        Example: 5

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      total_records:
        Type: integer
        Description: Total number of records
        Example: 40

      read:
        Type: integer
        Description: Number of read notifications
        Example: 4

      unread:
        Type: integer
        Description: Number of unread notifications
        Example: 3

      data:
        Type: array
        Items: 
          # Schema: Notification
          Type: object
          Properties:
            id:
              Type: string
              Example: "79d7e093-fa90-4f71-ae30-219f31abb761"

            message:
              Type: string
              Example: "8 new phrase(s) have been created in Project ABC"

            type:
              Type: enum
              Enum: ["invitation", "new_phrase", "added_to_entity"]
              Description: Type of notification. Should be used by client to decide how to display the notification.
              Example: "added_to_entity"

            data:
              Type: App\Data\NotificationPayloadData
              allOf:
                # Schema: NotificationPayloadData
                Type: object
                Properties:
                  entity_ids:
                    Type: array
                    Items: 
                      Type: string
                      Example: "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"



              Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

            created_at:
              Type: integer
              Example: 1764988634

            read_at:
              Type: integer
              Example: 1764988634


        Description: Array of notifications


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "page_count": 5,
  "records_per_page": 8,
  "total_records": 40,
  "read": 4,
  "unread": 3,
  "data": [
    {
      "id": "79d7e093-fa90-4f71-ae30-219f31abb761",
      "message": "8 new phrase(s) have been created in Project ABC",
      "type": "added_to_entity",
      "data": {
        "entity_ids": [
          "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"
        ]
      },
      "created_at": 1764988634,
      "read_at": 1764988634
    }
  ]
}
```
- 404: error
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/notifications/all/read
Summary: Mark All Notifications as Read
Operation ID: `84cbd55648ce59bf454c9f8ef548fcbe`

Description: Marks all the logged user notification as read.

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```

---

#### PATCH /api/notifications/all/unread
Summary: Mark All Notifications as Unread
Operation ID: `512d305021ccf2fa5fc97664315b5d0b`

Description: Marks all the logged user notification as unread.

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```

---

### Organization - Balance

#### GET /api/organizations/{organizationId}/credit/balance
Summary: Get Organization Credit Balance
Operation ID: `74c4df01beb0bc366494271d69ff1392`

Description: Get the credit balance of an organization.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransactionPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: BalanceTransaction
            Type: object
            Properties:
              id:
                Type: string
                Description: Transaction ID
                Example: "123e4567-e89b-12d3-a456-426614174001"

              entity_id:
                Type: string
                Description: Entity ID
                Example: "123e4567-e89b-12d3-a456-426614174000"

              entity_type:
                Type: string
                Description: Entity type (user, organization, project)
                Example: "user"

              amount:
                Type: number
                Format: float
                Description: Transaction amount
                Example: 50

              type:
                Type: enum
                Enum: ["credit", "auto_recharge", "machine_translation", "draw_from_account", "draw_from_organization", "prepaid_credits_invoiced", "free_credits_granted", "prepaid_credits_transfer", "free_credits_transfer"]
                Description: Transaction type
                Example: "prepaid_credits_transfer"

              balance_before:
                Type: number
                Format: float
                Description: Balance before transaction
                Example: 50

              balance_after:
                Type: number
                Format: float
                Description: Balance after transaction
                Example: 50

              prepaid_credit:
                Type: boolean
                Description: Pre-paid credit
                Example: true

              reference_entity_id:
                Type: string
                Description: Reference Entity ID
                Example: "ref_123"

              reference_entity_type:
                Type: string
                Description: Reference Entity Type
                Example: "user"

              payment_provider:
                Type: enum
                Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
                Description: Payment provider
                Example: "authorize_net"

              machine_translator:
                Type: enum
                Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                Description: Machine translator used in Transaction
                Example: "google"

              created_at:
                Type: integer
                Description: Transaction date
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174001",
      "entity_id": "123e4567-e89b-12d3-a456-426614174000",
      "entity_type": "user",
      "amount": 50,
      "type": "prepaid_credits_transfer",
      "balance_before": 50,
      "balance_after": 50,
      "prepaid_credit": true,
      "reference_entity_id": "ref_123",
      "reference_entity_type": "user",
      "payment_provider": "authorize_net",
      "machine_translator": "google",
      "created_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations/{organizationId}/credit
Summary: Add Credit to Organization
Operation ID: `6963ca0301ee44fcc1f27a9fa9149493`

Description: Add credit to an organization's balance.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: AddCreditRequest
  Type: object
  Properties:
    amount (Required):
      Type: number
      Format: float
      Description: Amount of credit to add.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Balance
        # Schema: Balance
        Type: object
        Properties:
          total_balance:
            Type: number
            Format: float
            Description: Total balance.
            Example: 100

          prepaid_credits_balance:
            Type: number
            Format: float
            Description: Prepaid credits balance.
            Example: 50

          free_credits_balance:
            Type: number
            Format: float
            Description: Free credits balance.
            Example: 50



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_balance": 100,
    "prepaid_credits_balance": 50,
    "free_credits_balance": 50
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/balance
Summary: Get Organization Balance
Operation ID: `0778d92d1c8d399fb0cfdbc3ad6f03fd`

Description: Get the balance of an organization.

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Balance
        # Schema: Balance
        Type: object
        Properties:
          total_balance:
            Type: number
            Format: float
            Description: Total balance.
            Example: 100

          prepaid_credits_balance:
            Type: number
            Format: float
            Description: Prepaid credits balance.
            Example: 50

          free_credits_balance:
            Type: number
            Format: float
            Description: Free credits balance.
            Example: 50



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_balance": 100,
    "prepaid_credits_balance": 50,
    "free_credits_balance": 50
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations/{organizationId}/credit/recharge-card
Summary: Set Organization Credit Card for Recharge
Operation ID: `a5fa40b067118f23a9ecfd714fdb61c8`

Description: Set a organization's credit card for automatic recharge.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: SetRechargeCreditCardRequest
  Type: object
  Properties:
    cc_id (Required):
      Type: string
      Description: The credit card id for the payment
      Example: "f3115745-511e-460b-9813-1094a5099bbb"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations/{organizationId}/credit/transfer/project/{projectId}
Summary: Transfer Credits from Organization to Project
Operation ID: `69ef8e8592cb11567415d76091c18cf4`

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): No description
  Type: integer
- `projectId` in path (Required): No description
  Type: string

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TransferCreditRequest
  Type: object
  Properties:
    prepaid_credits:
      Type: number
      Format: float
      Description: Amount of prepaid credits to transfer.
      Example: 100

    free_credits:
      Type: number
      Format: float
      Description: Amount of free credits to transfer.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransferResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceTransfer
        # Schema: BalanceTransfer
        Type: object
        Properties:
          source_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the source entity after transfer

          destination_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the destination entity after transfer



  Example Response:
```json
{
  "status": true,
  "data": {
    "source_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    },
    "destination_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    }
  }
}
```
- 400: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 403: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/credit/summary
Summary: Get Organization Credit Usage Summary
Operation ID: `60bdbe841b17a8147df301ca7362150d`

Description: Get the aggregated credit usage summary of an organization, optionally filtered by date range.

Security Requirements:
- bearerAuth

Parameters:
- `start_date` in query: No description
  Type: string
- `end_date` in query: No description
  Type: string

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceSummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceSummary
        # Schema: BalanceSummary
        Type: object
        Properties:
          prepaid_credits_used:
            Type: number
            Format: float
            Description: Total prepaid credits used in the period
            Example: 1000

          prepaid_credits_available:
            Type: number
            Format: float
            Description: Total prepaid credits currently available
            Example: 2000

          free_credits_used:
            Type: number
            Format: float
            Description: Total free credits used in the period
            Example: 500

          free_credits_available:
            Type: number
            Format: float
            Description: Total free credits currently available
            Example: 1500

          total_credits_used:
            Type: number
            Format: float
            Description: Total credits used in the period
            Example: 1500

          total_credits_available:
            Type: number
            Format: float
            Description: Total credits currently available
            Example: 2000



  Example Response:
```json
{
  "status": true,
  "data": {
    "prepaid_credits_used": 1000,
    "prepaid_credits_available": 2000,
    "free_credits_used": 500,
    "free_credits_available": 1500,
    "total_credits_used": 1500,
    "total_credits_available": 2000
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Organization

#### GET /api/organizations
Summary: List All Organizations
Operation ID: `38539e67fb3c20065e66e337a5fc6a37`

Description: Get a paginated list of all organizations. Pagination parameters are optional.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OrganizationPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Organization
            Type: object
            Properties:
              id:
                Type: string
                Example: "dd11c45f-2962-4400-82b8-6d353aeac909"

              name:
                Type: string
                Example: "Parisian-Hyatt"

              email:
                Type: string
                Example: "parker.judson@rempel.net"

              website_url:
                Type: string
                Example: "https://example.com"

              icon:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              logo:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              address:
                Type: App\Data\Address
                allOf:
                  # Schema: Address
                  Type: object
                  Properties:
                    address_1:
                      Type: string
                      Example: "Guachipelín de Escazú"

                    address_2:
                      Type: string
                      Example: "Ofibodegas #5"

                    city:
                      Type: string
                      Example: "Escazú"

                    state:
                      Type: string
                      Example: "San José"

                    zip:
                      Type: string
                      Example: "10203"

                    country_code:
                      Type: string
                      Example: "CR"

                    country:
                      Type: string
                      Example: "Costa Rica"



              settings:
                Type: App\Data\OrganizationSettingsData
                allOf:
                  # Schema: OrganizationSettingsData
                  Type: object
                  Properties:
                    use_translation_memory:
                      Type: boolean
                      Default: true
                      Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                      Example: true

                    machine_translate_new_phrases:
                      Type: boolean
                      Default: false
                      Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                      Example: true

                    use_machine_translations:
                      Type: boolean
                      Default: false
                      Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                      Example: true

                    translate_base_locale_only:
                      Type: boolean
                      Default: false
                      Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                      Example: true

                    machine_translator:
                      Type: enum
                      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                      Default: "default"
                      Description: Organization wide setting that determines the default machine translator to use in the projects.
                      Example: "deepl"

                    broadcast_translations:
                      Type: boolean
                      Default: false
                      Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                      Example: true

                    monthly_credit_usage_limit:
                      Type: number
                      Format: float
                      Description: Organization wide setting that determines the monthly usage limit for the organization.
                      Example: 20

                    auto_recharge_enabled:
                      Type: boolean
                      Default: false
                      Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                      Example: true

                    auto_recharge_threshold:
                      Type: number
                      Format: float
                      Description: Organization wide setting that determines the threshold for automatic recharge.
                      Example: 20

                    auto_recharge_amount:
                      Type: number
                      Format: float
                      Description: Organization wide setting that determines the amount to recharge.
                      Example: 20

                    auto_recharge_source:
                      Type: enum
                      Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                      Default: "account_balance_or_credit_card"
                      Description: Organization wide setting that determines the source of the automatic recharge.
                      Example: "organization_owner_balance"

                    allow_draw_projects:
                      Type: boolean
                      Default: false
                      Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                      Example: true

                    draw_projects_limit_monthly:
                      Type: number
                      Format: float
                      Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                      Example: 20



              stats:
                Type: App\Data\OrganizationStats
                allOf:
                  # Schema: OrganizationStats
                  Type: object
                  Properties:
                    projects:
                      Type: integer
                      Description: Total number of projects in the organization.
                      Example: 15

                    users:
                      Type: integer
                      Description: Total number of users in the organization.
                      Example: 25



              admin:
                Type: boolean
                Example: true

              role:
                Type: App\Data\RoleData
                allOf:
                  # Schema: RoleData
                  Type: object
                  Properties:
                    value:
                      Type: string
                      Description: Role value
                      Example: "organization_admin"

                    label:
                      Type: string
                      Description: Role label
                      Example: "Organization Admin"



              last_activity_at:
                Type: integer
                Example: 1764988634

              user_joined_at:
                Type: integer
                Description: Timestamp of when the user joined the organization
                Example: 1764988634

              created_at:
                Type: integer
                Example: 1764988634

              updated_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "dd11c45f-2962-4400-82b8-6d353aeac909",
      "name": "Parisian-Hyatt",
      "email": "parker.judson@rempel.net",
      "website_url": "https://example.com",
      "icon": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "logo": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "address": {
        "address_1": "Guachipelín de Escazú",
        "address_2": "Ofibodegas #5",
        "city": "Escazú",
        "state": "San José",
        "zip": "10203",
        "country_code": "CR",
        "country": "Costa Rica"
      },
      "settings": {
        "use_translation_memory": true,
        "machine_translate_new_phrases": true,
        "use_machine_translations": true,
        "translate_base_locale_only": true,
        "machine_translator": "deepl",
        "broadcast_translations": true,
        "monthly_credit_usage_limit": 20,
        "auto_recharge_enabled": true,
        "auto_recharge_threshold": 20,
        "auto_recharge_amount": 20,
        "auto_recharge_source": "organization_owner_balance",
        "allow_draw_projects": true,
        "draw_projects_limit_monthly": 20
      },
      "stats": {
        "projects": 15,
        "users": 25
      },
      "admin": true,
      "role": {
        "value": "organization_admin",
        "label": "Organization Admin"
      },
      "last_activity_at": 1764988634,
      "user_joined_at": 1764988634,
      "created_at": 1764988634,
      "updated_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations
Summary: Create New Organization
Operation ID: `705373d2182b3af62bdb4bed5c21fb1b`

Description: Create an organization. Only available for organization owners.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: OrganizationRequest
  Type: object
  Properties:
    name (Required):
      Type: string
      Example: "Miller Group"

    email (Required):
      Type: string
      Example: "tyrell46@gmail.com"

    website_url:
      Type: string
      Example: "https://www.example.com"

    icon:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"



    logo:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"



    settings:
      Type: App\Data\OrganizationSettingsData
      allOf:
        # Schema: OrganizationSettingsData
        Type: object
        Properties:
          use_translation_memory:
            Type: boolean
            Default: true
            Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
            Example: true

          machine_translate_new_phrases:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
            Example: true

          use_machine_translations:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
            Example: true

          translate_base_locale_only:
            Type: boolean
            Default: false
            Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
            Example: true

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Organization wide setting that determines the default machine translator to use in the projects.
            Example: "deepl"

          broadcast_translations:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
            Example: true

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: Organization wide setting that determines the monthly usage limit for the organization.
            Example: 20

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: Organization wide setting that determines the threshold for automatic recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: Organization wide setting that determines the amount to recharge.
            Example: 20

          auto_recharge_source:
            Type: enum
            Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
            Default: "account_balance_or_credit_card"
            Description: Organization wide setting that determines the source of the automatic recharge.
            Example: "organization_owner_balance"

          allow_draw_projects:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
            Example: true

          draw_projects_limit_monthly:
            Type: number
            Format: float
            Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
            Example: 20



    auto_recharge_credit_card_id:
      Type: string
      Example: "71bce8fc-3c4b-4a4e-9bbd-c2d4dd30c9f8"

    address:
      Type: App\Data\Address
      allOf:
        # Schema: Address
        Type: object
        Properties:
          address_1:
            Type: string
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Example: "Ofibodegas #5"

          city:
            Type: string
            Example: "Escazú"

          state:
            Type: string
            Example: "San José"

          zip:
            Type: string
            Example: "10203"

          country_code:
            Type: string
            Example: "CR"

          country:
            Type: string
            Example: "Costa Rica"





Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OrganizationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Organization
        # Schema: Organization
        Type: object
        Properties:
          id:
            Type: string
            Example: "dd11c45f-2962-4400-82b8-6d353aeac909"

          name:
            Type: string
            Example: "Parisian-Hyatt"

          email:
            Type: string
            Example: "parker.judson@rempel.net"

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          address:
            Type: App\Data\Address
            allOf:
              # Schema: Address
              Type: object
              Properties:
                address_1:
                  Type: string
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Example: "Escazú"

                state:
                  Type: string
                  Example: "San José"

                zip:
                  Type: string
                  Example: "10203"

                country_code:
                  Type: string
                  Example: "CR"

                country:
                  Type: string
                  Example: "Costa Rica"



          settings:
            Type: App\Data\OrganizationSettingsData
            allOf:
              # Schema: OrganizationSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Organization wide setting that determines the default machine translator to use in the projects.
                  Example: "deepl"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly usage limit for the organization.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                  Default: "account_balance_or_credit_card"
                  Description: Organization wide setting that determines the source of the automatic recharge.
                  Example: "organization_owner_balance"

                allow_draw_projects:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                  Example: true

                draw_projects_limit_monthly:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                  Example: 20



          stats:
            Type: App\Data\OrganizationStats
            allOf:
              # Schema: OrganizationStats
              Type: object
              Properties:
                projects:
                  Type: integer
                  Description: Total number of projects in the organization.
                  Example: 15

                users:
                  Type: integer
                  Description: Total number of users in the organization.
                  Example: 25



          admin:
            Type: boolean
            Example: true

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          last_activity_at:
            Type: integer
            Example: 1764988634

          user_joined_at:
            Type: integer
            Description: Timestamp of when the user joined the organization
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "dd11c45f-2962-4400-82b8-6d353aeac909",
    "name": "Parisian-Hyatt",
    "email": "parker.judson@rempel.net",
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "address": {
      "address_1": "Guachipelín de Escazú",
      "address_2": "Ofibodegas #5",
      "city": "Escazú",
      "state": "San José",
      "zip": "10203",
      "country_code": "CR",
      "country": "Costa Rica"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "deepl",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_owner_balance",
      "allow_draw_projects": true,
      "draw_projects_limit_monthly": 20
    },
    "stats": {
      "projects": 15,
      "users": 25
    },
    "admin": true,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "last_activity_at": 1764988634,
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}
Summary: Get Organization Details
Operation ID: `f5a82fa0fac627cd3c936286ee168867`

Description: Get a single organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OrganizationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Organization
        # Schema: Organization
        Type: object
        Properties:
          id:
            Type: string
            Example: "dd11c45f-2962-4400-82b8-6d353aeac909"

          name:
            Type: string
            Example: "Parisian-Hyatt"

          email:
            Type: string
            Example: "parker.judson@rempel.net"

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          address:
            Type: App\Data\Address
            allOf:
              # Schema: Address
              Type: object
              Properties:
                address_1:
                  Type: string
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Example: "Escazú"

                state:
                  Type: string
                  Example: "San José"

                zip:
                  Type: string
                  Example: "10203"

                country_code:
                  Type: string
                  Example: "CR"

                country:
                  Type: string
                  Example: "Costa Rica"



          settings:
            Type: App\Data\OrganizationSettingsData
            allOf:
              # Schema: OrganizationSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Organization wide setting that determines the default machine translator to use in the projects.
                  Example: "deepl"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly usage limit for the organization.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                  Default: "account_balance_or_credit_card"
                  Description: Organization wide setting that determines the source of the automatic recharge.
                  Example: "organization_owner_balance"

                allow_draw_projects:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                  Example: true

                draw_projects_limit_monthly:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                  Example: 20



          stats:
            Type: App\Data\OrganizationStats
            allOf:
              # Schema: OrganizationStats
              Type: object
              Properties:
                projects:
                  Type: integer
                  Description: Total number of projects in the organization.
                  Example: 15

                users:
                  Type: integer
                  Description: Total number of users in the organization.
                  Example: 25



          admin:
            Type: boolean
            Example: true

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          last_activity_at:
            Type: integer
            Example: 1764988634

          user_joined_at:
            Type: integer
            Description: Timestamp of when the user joined the organization
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "dd11c45f-2962-4400-82b8-6d353aeac909",
    "name": "Parisian-Hyatt",
    "email": "parker.judson@rempel.net",
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "address": {
      "address_1": "Guachipelín de Escazú",
      "address_2": "Ofibodegas #5",
      "city": "Escazú",
      "state": "San José",
      "zip": "10203",
      "country_code": "CR",
      "country": "Costa Rica"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "deepl",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_owner_balance",
      "allow_draw_projects": true,
      "draw_projects_limit_monthly": 20
    },
    "stats": {
      "projects": 15,
      "users": 25
    },
    "admin": true,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "last_activity_at": 1764988634,
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/organizations/{organizationId}
Summary: Delete Organization
Operation ID: `b94df993d21283f2cb860a446221cd3b`

Description: Delete an organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OrganizationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Organization
        # Schema: Organization
        Type: object
        Properties:
          id:
            Type: string
            Example: "dd11c45f-2962-4400-82b8-6d353aeac909"

          name:
            Type: string
            Example: "Parisian-Hyatt"

          email:
            Type: string
            Example: "parker.judson@rempel.net"

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          address:
            Type: App\Data\Address
            allOf:
              # Schema: Address
              Type: object
              Properties:
                address_1:
                  Type: string
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Example: "Escazú"

                state:
                  Type: string
                  Example: "San José"

                zip:
                  Type: string
                  Example: "10203"

                country_code:
                  Type: string
                  Example: "CR"

                country:
                  Type: string
                  Example: "Costa Rica"



          settings:
            Type: App\Data\OrganizationSettingsData
            allOf:
              # Schema: OrganizationSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Organization wide setting that determines the default machine translator to use in the projects.
                  Example: "deepl"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly usage limit for the organization.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                  Default: "account_balance_or_credit_card"
                  Description: Organization wide setting that determines the source of the automatic recharge.
                  Example: "organization_owner_balance"

                allow_draw_projects:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                  Example: true

                draw_projects_limit_monthly:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                  Example: 20



          stats:
            Type: App\Data\OrganizationStats
            allOf:
              # Schema: OrganizationStats
              Type: object
              Properties:
                projects:
                  Type: integer
                  Description: Total number of projects in the organization.
                  Example: 15

                users:
                  Type: integer
                  Description: Total number of users in the organization.
                  Example: 25



          admin:
            Type: boolean
            Example: true

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          last_activity_at:
            Type: integer
            Example: 1764988634

          user_joined_at:
            Type: integer
            Description: Timestamp of when the user joined the organization
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "dd11c45f-2962-4400-82b8-6d353aeac909",
    "name": "Parisian-Hyatt",
    "email": "parker.judson@rempel.net",
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "address": {
      "address_1": "Guachipelín de Escazú",
      "address_2": "Ofibodegas #5",
      "city": "Escazú",
      "state": "San José",
      "zip": "10203",
      "country_code": "CR",
      "country": "Costa Rica"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "deepl",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_owner_balance",
      "allow_draw_projects": true,
      "draw_projects_limit_monthly": 20
    },
    "stats": {
      "projects": 15,
      "users": 25
    },
    "admin": true,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "last_activity_at": 1764988634,
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/organizations/{organzationId}
Summary: Update Organization
Operation ID: `ee25615e229768458a3fe6bc81c051af`

Description: Update an organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: OrganizationUpdateRequest
  Type: object
  Properties:
    name:
      Type: string
      Example: "Kutch, Fritsch and Becker"

    email:
      Type: string
      Example: "gcorwin@yahoo.com"

    website_url:
      Type: string
      Example: "https://www.example.com"

    icon:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"



    logo:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"



    address:
      Type: App\Data\Address
      allOf:
        # Schema: Address
        Type: object
        Properties:
          address_1:
            Type: string
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Example: "Ofibodegas #5"

          city:
            Type: string
            Example: "Escazú"

          state:
            Type: string
            Example: "San José"

          zip:
            Type: string
            Example: "10203"

          country_code:
            Type: string
            Example: "CR"

          country:
            Type: string
            Example: "Costa Rica"



    auto_recharge_credit_card_id:
      Type: string
      Example: "4ece2d6d-ff66-437b-b422-c4761227b4f8"

    settings:
      Type: App\Data\OrganizationSettingsData
      allOf:
        # Schema: OrganizationSettingsData
        Type: object
        Properties:
          use_translation_memory:
            Type: boolean
            Default: true
            Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
            Example: true

          machine_translate_new_phrases:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
            Example: true

          use_machine_translations:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
            Example: true

          translate_base_locale_only:
            Type: boolean
            Default: false
            Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
            Example: true

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Organization wide setting that determines the default machine translator to use in the projects.
            Example: "deepl"

          broadcast_translations:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
            Example: true

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: Organization wide setting that determines the monthly usage limit for the organization.
            Example: 20

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: Organization wide setting that determines the threshold for automatic recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: Organization wide setting that determines the amount to recharge.
            Example: 20

          auto_recharge_source:
            Type: enum
            Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
            Default: "account_balance_or_credit_card"
            Description: Organization wide setting that determines the source of the automatic recharge.
            Example: "organization_owner_balance"

          allow_draw_projects:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
            Example: true

          draw_projects_limit_monthly:
            Type: number
            Format: float
            Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
            Example: 20





Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OrganizationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Organization
        # Schema: Organization
        Type: object
        Properties:
          id:
            Type: string
            Example: "dd11c45f-2962-4400-82b8-6d353aeac909"

          name:
            Type: string
            Example: "Parisian-Hyatt"

          email:
            Type: string
            Example: "parker.judson@rempel.net"

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          address:
            Type: App\Data\Address
            allOf:
              # Schema: Address
              Type: object
              Properties:
                address_1:
                  Type: string
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Example: "Escazú"

                state:
                  Type: string
                  Example: "San José"

                zip:
                  Type: string
                  Example: "10203"

                country_code:
                  Type: string
                  Example: "CR"

                country:
                  Type: string
                  Example: "Costa Rica"



          settings:
            Type: App\Data\OrganizationSettingsData
            allOf:
              # Schema: OrganizationSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Organization wide setting that determines the default machine translator to use in the projects.
                  Example: "deepl"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly usage limit for the organization.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
                  Default: "account_balance_or_credit_card"
                  Description: Organization wide setting that determines the source of the automatic recharge.
                  Example: "organization_owner_balance"

                allow_draw_projects:
                  Type: boolean
                  Default: false
                  Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
                  Example: true

                draw_projects_limit_monthly:
                  Type: number
                  Format: float
                  Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
                  Example: 20



          stats:
            Type: App\Data\OrganizationStats
            allOf:
              # Schema: OrganizationStats
              Type: object
              Properties:
                projects:
                  Type: integer
                  Description: Total number of projects in the organization.
                  Example: 15

                users:
                  Type: integer
                  Description: Total number of users in the organization.
                  Example: 25



          admin:
            Type: boolean
            Example: true

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          last_activity_at:
            Type: integer
            Example: 1764988634

          user_joined_at:
            Type: integer
            Description: Timestamp of when the user joined the organization
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "dd11c45f-2962-4400-82b8-6d353aeac909",
    "name": "Parisian-Hyatt",
    "email": "parker.judson@rempel.net",
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "address": {
      "address_1": "Guachipelín de Escazú",
      "address_2": "Ofibodegas #5",
      "city": "Escazú",
      "state": "San José",
      "zip": "10203",
      "country_code": "CR",
      "country": "Costa Rica"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "deepl",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_owner_balance",
      "allow_draw_projects": true,
      "draw_projects_limit_monthly": 20
    },
    "stats": {
      "projects": 15,
      "users": 25
    },
    "admin": true,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "last_activity_at": 1764988634,
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/settings
Summary: Get Organization Settings
Operation ID: `f6279a068d57d282f0d05a6b36893a1e`

Description: Get the settings for an organization. Will return an empty object if no settings are set for the organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OrganizationSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: OrganizationSettings
        # Schema: OrganizationSettings
        Type: object
        Properties:
          use_translation_memory:
            Type: boolean
            Default: true
            Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
            Example: true

          machine_translate_new_phrases:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
            Example: true

          use_machine_translations:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
            Example: true

          translate_base_locale_only:
            Type: boolean
            Default: false
            Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
            Example: true

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Organization wide setting that determines the default machine translator to use in the projects.
            Example: "deepl"

          broadcast_translations:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
            Example: true

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: Organization wide setting that determines the monthly usage limit for the organization.
            Example: 20

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: Organization wide setting that determines the threshold for automatic recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: Organization wide setting that determines the amount to recharge.
            Example: 20

          auto_recharge_source:
            Type: enum
            Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
            Default: "account_balance_or_credit_card"
            Description: Organization wide setting that determines the source of the automatic recharge.
            Example: "credit_card"

          allow_draw_projects:
            Type: boolean
            Default: false
            Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
            Example: true

          draw_projects_limit_monthly:
            Type: number
            Format: float
            Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
            Example: 20



  Example Response:
```json
{
  "status": true,
  "data": {
    "use_translation_memory": true,
    "machine_translate_new_phrases": true,
    "use_machine_translations": true,
    "translate_base_locale_only": true,
    "machine_translator": "deepl",
    "broadcast_translations": true,
    "monthly_credit_usage_limit": 20,
    "auto_recharge_enabled": true,
    "auto_recharge_threshold": 20,
    "auto_recharge_amount": 20,
    "auto_recharge_source": "credit_card",
    "allow_draw_projects": true,
    "draw_projects_limit_monthly": 20
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/organizations/{organizationId}/settings
Summary: Clear Organization Settings
Operation ID: `c4597893ccb418a9a6c2555a52bf7178`

Description: Clear the settings for an organization. Will clear the settings for the organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations/{organizationId}/transfer-ownership
Summary: Transfer Organization Ownership
Operation ID: `77d29b0b7da1a66c7f6ae277c209e0a7`

Description: Transfer ownership of the organization to a new user. Only available for organization owner.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: UserInvitationRequest
  Type: object
  Properties:
    user_id:
      Type: string
      Description: The id of the user to invite. If this field is provided, then the email field will be ignored.
      Example: "44b341b1-1946-45ff-a48c-eb33065cb8c3"

    email:
      Type: string
      Description: This field should be provided if the userId is not provided. It should be used for users who are not Langsys users. If the email provided is for an existing user then this will behave in the same way as sending the userId.
      Example: "mbatz@tromp.com"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Organization - Invitations

#### GET /api/organizations/{organizationId}/invitations
Summary: List Organization's Sent Invitations
Operation ID: `a0d7b4fcdb6b189eb38dc34294518cca`

Description: Get all sent invites for an organization. Only available for organization admins.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Invitation
            Type: object
            Properties:
              id:
                Type: string
                Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

              inviter_id:
                Type: string
                Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

              inviter:
                Type: string
                Example: "John Doe"

              invitee_id:
                Type: string
                Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

              invitee:
                Type: string
                Example: "John Miles"

              email:
                Type: string
                Example: "clement.terry@hotmail.com"

              entity_id:
                Type: string
                Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

              entity_type:
                Type: string
                Example: "Organization"

              entity_name:
                Type: string
                Example: "Flexmark"

              role:
                Type: string
                Example: "organization_admin"

              expires_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
      "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
      "inviter": "John Doe",
      "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
      "invitee": "John Miles",
      "email": "clement.terry@hotmail.com",
      "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
      "entity_type": "Organization",
      "entity_name": "Flexmark",
      "role": "organization_admin",
      "expires_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations/{organizationId}/invitations
Summary: Invite User to Organization
Operation ID: `947511ffc51f922c22849acc4db97d9f`

Description: Invite a user to an organization. Only available for organization admins.<br><br><i>**This function will send an invitation email to user.</i>

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: OrganizationInvitationRequest
  Type: object
  Properties:
    user_id:
      Type: string
      Description: The id of the user to invite. If provided, the email field will be ignored.
      Example: "52fde56d-2e34-4f44-aa9c-c8fcab083660"

    role:
      Type: enum
      Enum: ["organization_admin", "organization_user"]
      Default: "organization_user"
      Description: Role of the user in the organization
      Example: "organization_admin"

    email:
      Type: string
      Description: Provide an email if user is new. Email can also be provided for existing users if userId is not provided in the URI.
      Example: "vabbott@kerluke.com"

    disabled_projects:
      Type: array
      Items: 
        Type: string
        Example: "3f8f583a-1fcf-44ba-894e-23d9693d55ec"

      Description: A list of the ids of projects that will be disabled for the user. This list will sync with the currently disabled projects of the user.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Invitation
        # Schema: Invitation
        Type: object
        Properties:
          id:
            Type: string
            Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

          inviter_id:
            Type: string
            Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

          inviter:
            Type: string
            Example: "John Doe"

          invitee_id:
            Type: string
            Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

          invitee:
            Type: string
            Example: "John Miles"

          email:
            Type: string
            Example: "clement.terry@hotmail.com"

          entity_id:
            Type: string
            Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

          entity_type:
            Type: string
            Example: "Organization"

          entity_name:
            Type: string
            Example: "Flexmark"

          role:
            Type: string
            Example: "organization_admin"

          expires_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
    "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
    "inviter": "John Doe",
    "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
    "invitee": "John Miles",
    "email": "clement.terry@hotmail.com",
    "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
    "entity_type": "Organization",
    "entity_name": "Flexmark",
    "role": "organization_admin",
    "expires_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/invitations/all
Summary: List All Invitations for Admin's Organizations
Operation ID: `91f50905c78fb9ce4743c359be9dcea8`

Description: Retrieve all organizations invitations that have been sent where the logged user holds an admin role.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Invitation
            Type: object
            Properties:
              id:
                Type: string
                Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

              inviter_id:
                Type: string
                Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

              inviter:
                Type: string
                Example: "John Doe"

              invitee_id:
                Type: string
                Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

              invitee:
                Type: string
                Example: "John Miles"

              email:
                Type: string
                Example: "clement.terry@hotmail.com"

              entity_id:
                Type: string
                Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

              entity_type:
                Type: string
                Example: "Organization"

              entity_name:
                Type: string
                Example: "Flexmark"

              role:
                Type: string
                Example: "organization_admin"

              expires_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
      "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
      "inviter": "John Doe",
      "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
      "invitee": "John Miles",
      "email": "clement.terry@hotmail.com",
      "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
      "entity_type": "Organization",
      "entity_name": "Flexmark",
      "role": "organization_admin",
      "expires_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Organization - Projects

#### GET /api/organizations/{organizationId}/projects
Summary: List Organization's Projects
Operation ID: `657f1c9f357df412d2048e22b92eb83b`

Description: Get a paginated list of all projects in an organization. Pagination parameters are optional.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Project
            Type: object
            Properties:
              id:
                Type: string
                Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

              owner_id:
                Type: string
                Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

              title:
                Type: string
                Example: "Comercado"

              description:
                Type: string
                Example: "Translations for Comercado app"

              base_locale:
                Type: string
                Description: Locale in which project phrase strings are written.
                Example: "en-us"

              organization_id:
                Type: string
                Description: Id of organization the project belongs to
                Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

              organization_name:
                Type: string
                Example: "Konopelski, Ullrich and Wolf"

              target_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "fr-ca"

                Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

              default_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "es-cr"

                Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

              website_url:
                Type: string
                Example: "https://example.com"

              icon:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              logo:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              settings:
                Type: App\Data\ProjectSettingsData
                allOf:
                  # Schema: ProjectSettingsData
                  Type: object
                  Properties:
                    use_translation_memory:
                      Type: boolean
                      Default: true
                      Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                      Example: true

                    machine_translate_new_phrases:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                      Example: true

                    use_machine_translations:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                      Example: true

                    translate_base_locale_only:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                      Example: true

                    machine_translator:
                      Type: enum
                      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                      Default: "default"
                      Description: Project wide setting that determines which machine translator to use.
                      Example: "default"

                    broadcast_translations:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                      Example: true

                    monthly_credit_usage_limit:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the monthly usage limit for the project.
                      Example: 20

                    auto_recharge_enabled:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                      Example: true

                    auto_recharge_threshold:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the threshold for automatic recharge.
                      Example: 20

                    auto_recharge_amount:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the amount to recharge.
                      Example: 20

                    auto_recharge_source:
                      Type: enum
                      Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                      Default: "organization_balance_or_credit_card"
                      Description: Project wide setting that determines the source of the automatic recharge.
                      Example: "organization_balance_or_credit_card"



              admin:
                Type: boolean
                Example: true

              last_activity_at:
                Type: integer
                Example: 1764988634

              totals:
                Type: App\Data\TranslationTotals\GeneralProjectTotals
                allOf:
                  # Schema: GeneralProjectTotals
                  Type: object
                  Properties:
                    phrases:
                      Type: integer
                      Description: Total number of phrases in project.
                      Example: 291

                    words:
                      Type: integer
                      Description: Total number of words in project.
                      Example: 755

                    words_to_translate:
                      Type: integer
                      Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                      Example: 3020

                    target_locales:
                      Type: integer
                      Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                      Example: 4



              role:
                Type: App\Data\RoleData
                allOf:
                  # Schema: RoleData
                  Type: object
                  Properties:
                    value:
                      Type: string
                      Description: Role value
                      Example: "organization_admin"

                    label:
                      Type: string
                      Description: Role label
                      Example: "Organization Admin"



              user_joined_at:
                Type: integer
                Description: Timestamp when the user joined the project or when they got access to it
                Example: 1764988634

              created_at:
                Type: integer
                Example: 1764988634

              updated_at:
                Type: integer
                Example: 1764988634

              deleted_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
      "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
      "title": "Comercado",
      "description": "Translations for Comercado app",
      "base_locale": "en-us",
      "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
      "organization_name": "Konopelski, Ullrich and Wolf",
      "target_locales": [
        "fr-ca"
      ],
      "default_locales": [
        "es-cr"
      ],
      "website_url": "https://example.com",
      "icon": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "logo": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "settings": {
        "use_translation_memory": true,
        "machine_translate_new_phrases": true,
        "use_machine_translations": true,
        "translate_base_locale_only": true,
        "machine_translator": "default",
        "broadcast_translations": true,
        "monthly_credit_usage_limit": 20,
        "auto_recharge_enabled": true,
        "auto_recharge_threshold": 20,
        "auto_recharge_amount": 20,
        "auto_recharge_source": "organization_balance_or_credit_card"
      },
      "admin": true,
      "last_activity_at": 1764988634,
      "totals": {
        "phrases": 291,
        "words": 755,
        "words_to_translate": 3020,
        "target_locales": 4
      },
      "role": {
        "value": "organization_admin",
        "label": "Organization Admin"
      },
      "user_joined_at": 1764988634,
      "created_at": 1764988634,
      "updated_at": 1764988634,
      "deleted_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Organization - Reports

#### GET /api/organizations/{organizationId}/reports/activity
Summary: Get Organization Activity Report
Operation ID: `b1a1d9a41ffc4b57a716f525527fcd8e`

Description: Get the activity for an specific organization

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: DateRangeRequest
  Type: object
  Properties:
    start_date:
      Type: string
      Description: Start date to filter by
      Example: "2024-01-01"

    end_date:
      Type: string
      Description: End date to filter by
      Example: "2024-01-31"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ActivityResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Activity
        # Schema: Activity
        Type: object
        Properties:
          date:
            Type: string
            Description: Log date.
            Example: "130"

          get_requests:
            Type: integer
            Description: Total number of get requests.
            Example: 130

          post_requests:
            Type: integer
            Description: Total number of post requests.
            Example: 130

          patch_requests:
            Type: integer
            Description: Total number of patch requests.
            Example: 130

          delete_requests:
            Type: integer
            Description: Total number of delete requests.
            Example: 130



  Example Response:
```json
{
  "status": true,
  "data": {
    "date": "130",
    "get_requests": 130,
    "post_requests": 130,
    "patch_requests": 130,
    "delete_requests": 130
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/reports/activity-summary
Summary: Get Organization Activity Summary
Operation ID: `54cf22565047c4173a47aff704952bba`

Description: Get the activity summary for an specific organization.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: DateRangeRequest
  Type: object
  Properties:
    start_date:
      Type: string
      Description: Start date to filter by
      Example: "2024-01-01"

    end_date:
      Type: string
      Description: End date to filter by
      Example: "2024-01-31"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ActivitySummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ActivitySummary
        # Schema: ActivitySummary
        Type: object
        Properties:
          get_requests:
            Type: integer
            Description: Total number of get requests.
            Example: 130

          post_requests:
            Type: integer
            Description: Total number of post requests.
            Example: 130

          patch_requests:
            Type: integer
            Description: Total number of patch requests.
            Example: 130

          delete_requests:
            Type: integer
            Description: Total number of delete requests.
            Example: 130



  Example Response:
```json
{
  "status": true,
  "data": {
    "get_requests": 130,
    "post_requests": 130,
    "patch_requests": 130,
    "delete_requests": 130
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/reports/machine-translation-transactions-summary
Summary: Get Organization Machine Translation Usage Summary
Operation ID: `6badb70a972a92b335b4bf3aa44aa037`

Description: Get machine translation transactions summary for an specific organization.

Security Requirements:
- bearerAuth

Parameters:
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: MachineTranslationSummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: MachineTranslationSummary
        # Schema: MachineTranslationSummary
        Type: object
        Properties:
          total_phrases:
            Type: integer
            Description: Total number of phrases translated.
            Example: 130

          total_words:
            Type: integer
            Description: Total number of words translated.
            Example: 1520

          billing_amount:
            Type: number
            Format: float
            Description: Total billing amount of translations.
            Example: 11520



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_phrases": 130,
    "total_words": 1520,
    "billing_amount": 11520
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/users/{userId}/reports/activity
Summary: Get User Activity By Day
Operation ID: `f2fef9a31da6dbc4bd672dfe1aabecc5`

Description: Get the activity for an specific user.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ActivityResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Activity
        # Schema: Activity
        Type: object
        Properties:
          date:
            Type: string
            Description: Log date.
            Example: "130"

          get_requests:
            Type: integer
            Description: Total number of get requests.
            Example: 130

          post_requests:
            Type: integer
            Description: Total number of post requests.
            Example: 130

          patch_requests:
            Type: integer
            Description: Total number of patch requests.
            Example: 130

          delete_requests:
            Type: integer
            Description: Total number of delete requests.
            Example: 130



  Example Response:
```json
{
  "status": true,
  "data": {
    "date": "130",
    "get_requests": 130,
    "post_requests": 130,
    "patch_requests": 130,
    "delete_requests": 130
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/users/{userId}/reports/activity-summary
Summary: Get User Activity Summary
Operation ID: `89c85a7f8be0d239bca873793b9f634f`

Description: Get the activity summary for an specific user.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ActivitySummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ActivitySummary
        # Schema: ActivitySummary
        Type: object
        Properties:
          get_requests:
            Type: integer
            Description: Total number of get requests.
            Example: 130

          post_requests:
            Type: integer
            Description: Total number of post requests.
            Example: 130

          patch_requests:
            Type: integer
            Description: Total number of patch requests.
            Example: 130

          delete_requests:
            Type: integer
            Description: Total number of delete requests.
            Example: 130



  Example Response:
```json
{
  "status": true,
  "data": {
    "get_requests": 130,
    "post_requests": 130,
    "patch_requests": 130,
    "delete_requests": 130
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Organization - Users

#### GET /api/organizations/{organizationId}/users
Summary: List Organization Users
Operation ID: `37a57e93defda8f967670ec3f9d20f52`

Description: Get a list of users who have access to this organization. Pagination parameters are optional.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserOrganizationAccessPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: UserOrganizationAccess
            Type: object
            Properties:
              id:
                Type: string
                Example: "8c07c1b3-5dc8-4829-97a3-49b56b235b0c"

              firstname:
                Type: string
                Example: "Cleora"

              lastname:
                Type: string
                Example: "Hammes"

              avatar:
                Type: App\Data\Avatar
                allOf:
                  # Schema: Avatar
                  Type: object
                  Properties:
                    width:
                      Type: integer
                      Example: 481

                    height:
                      Type: integer
                      Example: 396

                    original_url:
                      Type: string
                      Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                    thumb_url:
                      Type: string
                      Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                    medium_url:
                      Type: string
                      Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                    id:
                      Type: string
                      Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                    path:
                      Type: string
                      Description: Path of local file
                      Example: "/public/images"



              last_activity_at:
                Type: integer
                Example: 1740633734

              role:
                Type: App\Data\RoleData
                allOf:
                  # Schema: RoleData
                  Type: object
                  Properties:
                    value:
                      Type: string
                      Description: Role value
                      Example: "organization_admin"

                    label:
                      Type: string
                      Description: Role label
                      Example: "Organization Admin"



              invited_by:
                Type: App\Data\UserData
                allOf:
                  # Schema: UserData
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

                    firstname:
                      Type: string
                      Example: "Modesto"

                    lastname:
                      Type: string
                      Example: "Green"

                    email:
                      Type: string
                      Example: "schaden.laron@gmail.com"

                    phone:
                      Type: string
                      Example: "(401) 259-3149"

                    locale:
                      Type: string
                      Example: "kk_KZ"

                    last_seen_at:
                      Type: integer
                      Example: 1764988634

                    avatar:
                      Type: App\Data\Avatar
                      allOf:
                        # Schema: Avatar
                        Type: object
                        Properties:
                          width:
                            Type: integer
                            Example: 481

                          height:
                            Type: integer
                            Example: 396

                          original_url:
                            Type: string
                            Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                          thumb_url:
                            Type: string
                            Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                          medium_url:
                            Type: string
                            Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                          id:
                            Type: string
                            Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                          path:
                            Type: string
                            Description: Path of local file
                            Example: "/public/images"


                      Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found





        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "8c07c1b3-5dc8-4829-97a3-49b56b235b0c",
      "firstname": "Cleora",
      "lastname": "Hammes",
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      },
      "last_activity_at": 1740633734,
      "role": {
        "value": "organization_admin",
        "label": "Organization Admin"
      },
      "invited_by": {
        "id": "dd3fab24-3954-407f-ac04-7e590ca5f632",
        "firstname": "Modesto",
        "lastname": "Green",
        "email": "schaden.laron@gmail.com",
        "phone": "(401) 259-3149",
        "locale": "kk_KZ",
        "last_seen_at": 1764988634,
        "avatar": {
          "width": 481,
          "height": 396,
          "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
          "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
          "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
          "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
          "path": "/public/images"
        }
      }
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/organizations/{organizationId}/users/settings
Summary: Get User Organization Settings
Operation ID: `17c6fa71abf5a629cb6130738626bbc6`

Description: Get overridden user settings for an organization. Will return an empty object if no settings are overridden for the user in the organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserSettings
        # Schema: UserSettings
        Type: object
        Properties:
          notifications:
            Type: App\Data\UserNotificationSettings
            allOf:
              # Schema: UserNotificationSettings
              Type: object
              Properties:
                new_phrase:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                invitation:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                added_to_entity:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


            Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: The maximum amount that can be drawn from the monthly balance of the user.
            Example: 100

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Whether auto recharge is enabled for the user
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
            Example: 20

          allow_draw_organizations:
            Type: boolean
            Default: true
            Description: The allow draw organizations for the user
            Example: true

          draw_organizations_limit_monthly:
            Type: number
            Format: float
            Description: The draw organizations limit monthly for the user
            Example: 100



  Example Response:
```json
{
  "status": true,
  "data": {
    "notifications": {
      "new_phrase": [
        "broadcast"
      ],
      "invitation": [
        "broadcast"
      ],
      "added_to_entity": [
        "broadcast"
      ]
    },
    "monthly_credit_usage_limit": 100,
    "auto_recharge_enabled": true,
    "auto_recharge_threshold": 20,
    "auto_recharge_amount": 20,
    "allow_draw_organizations": true,
    "draw_organizations_limit_monthly": 100
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/organizations/{organizationId}/users/settings
Summary: Clear User Organization Settings
Operation ID: `135cd2425a09cf0848b1c5df00662368`

Description: Clear overridden user settings for an organization. Will do nothing if no overriden settings exist.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/organizations/{organizationId}/users/settings
Summary: Update User's Organization Settings
Operation ID: `2488bb00bf638d7befc4dc3d2d133591`

Description: Update authenticated user settings for the given organization.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: UserOrganizationSettingsRequest
  Type: object
  Properties:
    notifications:
      Type: App\Data\UserNotificationSettings
      allOf:
        # Schema: UserNotificationSettings
        Type: object
        Properties:
          new_phrase:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

          invitation:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

          added_to_entity:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


      Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserSettings
        # Schema: UserSettings
        Type: object
        Properties:
          notifications:
            Type: App\Data\UserNotificationSettings
            allOf:
              # Schema: UserNotificationSettings
              Type: object
              Properties:
                new_phrase:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                invitation:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                added_to_entity:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


            Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: The maximum amount that can be drawn from the monthly balance of the user.
            Example: 100

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Whether auto recharge is enabled for the user
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
            Example: 20

          allow_draw_organizations:
            Type: boolean
            Default: true
            Description: The allow draw organizations for the user
            Example: true

          draw_organizations_limit_monthly:
            Type: number
            Format: float
            Description: The draw organizations limit monthly for the user
            Example: 100



  Example Response:
```json
{
  "status": true,
  "data": {
    "notifications": {
      "new_phrase": [
        "broadcast"
      ],
      "invitation": [
        "broadcast"
      ],
      "added_to_entity": [
        "broadcast"
      ]
    },
    "monthly_credit_usage_limit": 100,
    "auto_recharge_enabled": true,
    "auto_recharge_threshold": 20,
    "auto_recharge_amount": 20,
    "allow_draw_organizations": true,
    "draw_organizations_limit_monthly": 100
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/organizations/{organizationId}/users/{userId}
Summary: Add User to Organization
Operation ID: `36e1e9217144c83e32c850c30a676065`

Description: Add a user to an organization. This endpoint activates the user immediately. Only sends a notification email, it does not follow the invitation workflow. <br><br><i>**This function will send a notification email to user.</i>

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: OrganizationUserRequest
  Type: object
  Properties:
    role:
      Type: enum
      Enum: ["organization_admin", "organization_user"]
      Default: "organization_user"
      Description: Role of the user in the organization
      Example: "organization_user"

    disabled_projects:
      Type: array
      Items: 
        Type: string
        Example: "3f8f583a-1fcf-44ba-894e-23d9693d55ec"

      Description: A list of the ids of projects that will be disabled for the user. This list will sync with the currently disabled projects of the user.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserOrganizationAccessResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserOrganizationAccess
        # Schema: UserOrganizationAccess
        Type: object
        Properties:
          id:
            Type: string
            Example: "8c07c1b3-5dc8-4829-97a3-49b56b235b0c"

          firstname:
            Type: string
            Example: "Cleora"

          lastname:
            Type: string
            Example: "Hammes"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          last_activity_at:
            Type: integer
            Example: 1740633734

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          invited_by:
            Type: App\Data\UserData
            allOf:
              # Schema: UserData
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

                firstname:
                  Type: string
                  Example: "Modesto"

                lastname:
                  Type: string
                  Example: "Green"

                email:
                  Type: string
                  Example: "schaden.laron@gmail.com"

                phone:
                  Type: string
                  Example: "(401) 259-3149"

                locale:
                  Type: string
                  Example: "kk_KZ"

                last_seen_at:
                  Type: integer
                  Example: 1764988634

                avatar:
                  Type: App\Data\Avatar
                  allOf:
                    # Schema: Avatar
                    Type: object
                    Properties:
                      width:
                        Type: integer
                        Example: 481

                      height:
                        Type: integer
                        Example: 396

                      original_url:
                        Type: string
                        Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                      thumb_url:
                        Type: string
                        Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                      medium_url:
                        Type: string
                        Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                      id:
                        Type: string
                        Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                      path:
                        Type: string
                        Description: Path of local file
                        Example: "/public/images"


                  Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "8c07c1b3-5dc8-4829-97a3-49b56b235b0c",
    "firstname": "Cleora",
    "lastname": "Hammes",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "last_activity_at": 1740633734,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "invited_by": {
      "id": "dd3fab24-3954-407f-ac04-7e590ca5f632",
      "firstname": "Modesto",
      "lastname": "Green",
      "email": "schaden.laron@gmail.com",
      "phone": "(401) 259-3149",
      "locale": "kk_KZ",
      "last_seen_at": 1764988634,
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      }
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/organizations/{organizationId}/users/{userId}
Summary: Remove User from Organization
Operation ID: `6222e11c899e15738709438f3c2dc186`

Description: Remove user from organization. Only available for organization admins. Only the organization owner can remove an admin.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserExtendedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserExtended
        # Schema: UserExtended
        Type: object
        Properties:
          id:
            Type: string
            Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

          firstname:
            Type: string
            Example: "Estelle"

          lastname:
            Type: string
            Example: "McLaughlin"

          email:
            Type: string
            Example: "schuppe.elmore@gmail.com"

          phone:
            Type: string
            Example: "(630) 622-5121"

          locale:
            Type: string
            Example: "es-cr"

          last_seen_at:
            Type: integer
            Description: Unix timestamp indicating last time the user interacted with the system.
            Example: 1764988634

          created_at:
            Type: integer
            Description: Unix timestamp indicating creation date.
            Example: 1764988634

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"


            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

          source_locales:
            Type: array
            Items: 
              Type: string
              Example: "en_MH"

            Description: List of locales user can translate from

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "ps_AF"

            Description: List of locales user can translate to

          settings:
            Type: App\Data\UserSettingsData
            allOf:
              # Schema: UserSettingsData
              Type: object
              Properties:
                notifications:
                  Type: App\Data\UserNotificationSettings
                  allOf:
                    # Schema: UserNotificationSettings
                    Type: object
                    Properties:
                      new_phrase:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      invitation:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      added_to_entity:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


                  Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: The maximum amount that can be drawn from the monthly balance of the user.
                  Example: 100

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Whether auto recharge is enabled for the user
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
                  Example: 20

                allow_draw_organizations:
                  Type: boolean
                  Default: true
                  Description: The allow draw organizations for the user
                  Example: true

                draw_organizations_limit_monthly:
                  Type: number
                  Format: float
                  Description: The draw organizations limit monthly for the user
                  Example: 100





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc",
    "firstname": "Estelle",
    "lastname": "McLaughlin",
    "email": "schuppe.elmore@gmail.com",
    "phone": "(630) 622-5121",
    "locale": "es-cr",
    "last_seen_at": 1764988634,
    "created_at": 1764988634,
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "source_locales": [
      "en_MH"
    ],
    "target_locales": [
      "ps_AF"
    ],
    "settings": {
      "notifications": {
        "new_phrase": [
          "broadcast"
        ],
        "invitation": [
          "broadcast"
        ],
        "added_to_entity": [
          "broadcast"
        ]
      },
      "monthly_credit_usage_limit": 100,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "allow_draw_organizations": true,
      "draw_organizations_limit_monthly": 100
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/organizations/{organizationId}/users/{userId}
Summary: Update User's Organization Access
Operation ID: `430d8f549a985b25be645a3f1504c5b1`

Description: Edit user role and project access to an organization. A user will be authorized to edit another if requester is super admin, organization owner, or the user who invited the target user.

Security Requirements:
- bearerAuth

Parameters:
- `organizationId` in path (Required): Id of organization
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: OrganizationUserRequest
  Type: object
  Properties:
    role:
      Type: enum
      Enum: ["organization_admin", "organization_user"]
      Default: "organization_user"
      Description: Role of the user in the organization
      Example: "organization_user"

    disabled_projects:
      Type: array
      Items: 
        Type: string
        Example: "3f8f583a-1fcf-44ba-894e-23d9693d55ec"

      Description: A list of the ids of projects that will be disabled for the user. This list will sync with the currently disabled projects of the user.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserOrganizationAccessResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserOrganizationAccess
        # Schema: UserOrganizationAccess
        Type: object
        Properties:
          id:
            Type: string
            Example: "8c07c1b3-5dc8-4829-97a3-49b56b235b0c"

          firstname:
            Type: string
            Example: "Cleora"

          lastname:
            Type: string
            Example: "Hammes"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          last_activity_at:
            Type: integer
            Example: 1740633734

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          invited_by:
            Type: App\Data\UserData
            allOf:
              # Schema: UserData
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

                firstname:
                  Type: string
                  Example: "Modesto"

                lastname:
                  Type: string
                  Example: "Green"

                email:
                  Type: string
                  Example: "schaden.laron@gmail.com"

                phone:
                  Type: string
                  Example: "(401) 259-3149"

                locale:
                  Type: string
                  Example: "kk_KZ"

                last_seen_at:
                  Type: integer
                  Example: 1764988634

                avatar:
                  Type: App\Data\Avatar
                  allOf:
                    # Schema: Avatar
                    Type: object
                    Properties:
                      width:
                        Type: integer
                        Example: 481

                      height:
                        Type: integer
                        Example: 396

                      original_url:
                        Type: string
                        Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                      thumb_url:
                        Type: string
                        Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                      medium_url:
                        Type: string
                        Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                      id:
                        Type: string
                        Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                      path:
                        Type: string
                        Description: Path of local file
                        Example: "/public/images"


                  Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "8c07c1b3-5dc8-4829-97a3-49b56b235b0c",
    "firstname": "Cleora",
    "lastname": "Hammes",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "last_activity_at": 1740633734,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "invited_by": {
      "id": "dd3fab24-3954-407f-ac04-7e590ca5f632",
      "firstname": "Modesto",
      "lastname": "Green",
      "email": "schaden.laron@gmail.com",
      "phone": "(401) 259-3149",
      "locale": "kk_KZ",
      "last_seen_at": 1764988634,
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      }
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Payment - Methods

#### GET /api/payment-methods
Summary: Get credit cards belonging to user
Operation ID: `13fe0f3efa27dd578bcd35262f64873a`

Description: Fetch all available credit cards for the authenticated user. Pagination parameters are optional.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: PaymentMethodPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: PaymentMethod
            Type: object
            Properties:
              id:
                Type: string
                Description: Credit card id.
                Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

              cc_mask:
                Type: string
                Description: Masked credit card number.
                Example: "4111-1111-1111-1111"

              cc_brand:
                Type: string
                Description: Type of card.
                Example: "VISA"

              cc_name:
                Type: string
                Description: Name on the credit card.
                Example: "Joe Doe"

              cc_month:
                Type: string
                Description: Expiration month.
                Example: "01"

              cc_year:
                Type: string
                Description: Expiration year.
                Example: "2025"

              default:
                Type: boolean
                Description: Is default payment method.
                Example: true

              address_1:
                Type: string
                Description: Primary billing address line
                Example: "Guachipelín de Escazú"

              address_2:
                Type: string
                Description: Secondary billing address line
                Example: "Ofibodegas #5"

              city:
                Type: string
                Description: City
                Example: "Escazú"

              state:
                Type: string
                Description: State/Province
                Example: "San José"

              zip:
                Type: string
                Description: ZIP/Postal code
                Example: "10203"



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "19b3b92a-3399-4a3f-a121-afbd89a75d22",
      "cc_mask": "4111-1111-1111-1111",
      "cc_brand": "VISA",
      "cc_name": "Joe Doe",
      "cc_month": "01",
      "cc_year": "2025",
      "default": true,
      "address_1": "Guachipelín de Escazú",
      "address_2": "Ofibodegas #5",
      "city": "Escazú",
      "state": "San José",
      "zip": "10203"
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/payment-methods
Summary: Add a new credit card
Operation ID: `2ee79d110be850e55361d4ed8e4e8d15`

Description: Add a new credit card for the authenticated user.

Security Requirements:
- bearerAuth

Request Body:
Required: Yes
Content-Type: `application/json`
Schema:
  # Schema: CreditCardRequest
  Type: object
  Properties:
    cc_number (Required):
      Type: string
      Description: Full credit card number.
      Example: "4111111111111111"

    cc_month (Required):
      Type: string
      Description: Card expiration month (2 digits).
      Example: "02"

    cc_year (Required):
      Type: string
      Description: Card expiration year (4 digits).
      Example: "2025"

    cc_name:
      Type: string
      Description: Cardholder name as it appears on the card.
      Example: "John Doe"

    cc_cvv:
      Type: string
      Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
      Example: "123"

    country_code:
      Type: string
      Description: Two-letter ISO country code where the card was issued or the billing address is located.
      Example: "US"

    billing_address:
      Type: App\Data\BillingAddressData
      allOf:
        # Schema: BillingAddressData
        Type: object
        Properties:
          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"


      Description: Billing address information

    is_default:
      Type: boolean
      Default: false
      Description: Set this card as the default payment method.
      Example: true



Responses:
- 200: Credit card added successfully
  Content-Type: `application/json`
  Schema:
    # Schema: PaymentMethodResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: PaymentMethod
        # Schema: PaymentMethod
        Type: object
        Properties:
          id:
            Type: string
            Description: Credit card id.
            Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

          cc_mask:
            Type: string
            Description: Masked credit card number.
            Example: "4111-1111-1111-1111"

          cc_brand:
            Type: string
            Description: Type of card.
            Example: "VISA"

          cc_name:
            Type: string
            Description: Name on the credit card.
            Example: "Joe Doe"

          cc_month:
            Type: string
            Description: Expiration month.
            Example: "01"

          cc_year:
            Type: string
            Description: Expiration year.
            Example: "2025"

          default:
            Type: boolean
            Description: Is default payment method.
            Example: true

          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "19b3b92a-3399-4a3f-a121-afbd89a75d22",
    "cc_mask": "4111-1111-1111-1111",
    "cc_brand": "VISA",
    "cc_name": "Joe Doe",
    "cc_month": "01",
    "cc_year": "2025",
    "default": true,
    "address_1": "Guachipelín de Escazú",
    "address_2": "Ofibodegas #5",
    "city": "Escazú",
    "state": "San José",
    "zip": "10203"
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: Validation Error or Invalid Card
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/payment-methods/{paymentMethodId}
Summary: Get Credit Card
Operation ID: `33e370fd9ff4faf247e85970ab3291aa`

Description: Get credit card belonging to user

Security Requirements:
- bearerAuth

Parameters:
- `paymentMethodId` in path (Required): Id of payment method
  Type: string
  Example: `"03b8eacb-197c-4b83-ab5a-4ba88820ada1"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: PaymentMethodResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: PaymentMethod
        # Schema: PaymentMethod
        Type: object
        Properties:
          id:
            Type: string
            Description: Credit card id.
            Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

          cc_mask:
            Type: string
            Description: Masked credit card number.
            Example: "4111-1111-1111-1111"

          cc_brand:
            Type: string
            Description: Type of card.
            Example: "VISA"

          cc_name:
            Type: string
            Description: Name on the credit card.
            Example: "Joe Doe"

          cc_month:
            Type: string
            Description: Expiration month.
            Example: "01"

          cc_year:
            Type: string
            Description: Expiration year.
            Example: "2025"

          default:
            Type: boolean
            Description: Is default payment method.
            Example: true

          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "19b3b92a-3399-4a3f-a121-afbd89a75d22",
    "cc_mask": "4111-1111-1111-1111",
    "cc_brand": "VISA",
    "cc_name": "Joe Doe",
    "cc_month": "01",
    "cc_year": "2025",
    "default": true,
    "address_1": "Guachipelín de Escazú",
    "address_2": "Ofibodegas #5",
    "city": "Escazú",
    "state": "San José",
    "zip": "10203"
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/payment-methods/{paymentMethodId}
Summary: Delete a credit card
Operation ID: `2ecc1e9bddd1a3bfecbbc8c8a28778ab`

Description: Delete an existing credit card for the authenticated user.

Security Requirements:
- bearerAuth

Parameters:
- `paymentMethodId` in path (Required): ID of the credit card
  Type: string

Responses:
- 200: Credit card deleted successfully
  Content-Type: `application/json`
  Schema:
    # Schema: PaymentMethodResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: PaymentMethod
        # Schema: PaymentMethod
        Type: object
        Properties:
          id:
            Type: string
            Description: Credit card id.
            Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

          cc_mask:
            Type: string
            Description: Masked credit card number.
            Example: "4111-1111-1111-1111"

          cc_brand:
            Type: string
            Description: Type of card.
            Example: "VISA"

          cc_name:
            Type: string
            Description: Name on the credit card.
            Example: "Joe Doe"

          cc_month:
            Type: string
            Description: Expiration month.
            Example: "01"

          cc_year:
            Type: string
            Description: Expiration year.
            Example: "2025"

          default:
            Type: boolean
            Description: Is default payment method.
            Example: true

          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "19b3b92a-3399-4a3f-a121-afbd89a75d22",
    "cc_mask": "4111-1111-1111-1111",
    "cc_brand": "VISA",
    "cc_name": "Joe Doe",
    "cc_month": "01",
    "cc_year": "2025",
    "default": true,
    "address_1": "Guachipelín de Escazú",
    "address_2": "Ofibodegas #5",
    "city": "Escazú",
    "state": "San José",
    "zip": "10203"
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 404: error
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/payment-methods/{paymentMethodId}
Summary: Update a credit card
Operation ID: `5585d8403f78487545133ad8a5390e71`

Description: Update an existing credit card for the authenticated user.

Security Requirements:
- bearerAuth

Parameters:
- `paymentMethodId` in path (Required): ID of the credit card
  Type: string

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: CreditCardUpdateRequest
  Type: object
  Properties:
    cc_name:
      Type: string
      Description: Cardholder name as it appears on the card.
      Example: "John Doe"

    cc_month:
      Type: string
      Description: Card expiration month (2 digits).
      Example: "02"

    cc_year:
      Type: string
      Description: Card expiration year (4 digits).
      Example: "2025"

    cc_cvv:
      Type: string
      Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
      Example: "123"

    billing_address:
      Type: App\Data\BillingAddressData
      allOf:
        # Schema: BillingAddressData
        Type: object
        Properties:
          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"


      Description: Billing address information



Responses:
- 200: Credit card updated successfully
  Content-Type: `application/json`
  Schema:
    # Schema: PaymentMethodResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: PaymentMethod
        # Schema: PaymentMethod
        Type: object
        Properties:
          id:
            Type: string
            Description: Credit card id.
            Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

          cc_mask:
            Type: string
            Description: Masked credit card number.
            Example: "4111-1111-1111-1111"

          cc_brand:
            Type: string
            Description: Type of card.
            Example: "VISA"

          cc_name:
            Type: string
            Description: Name on the credit card.
            Example: "Joe Doe"

          cc_month:
            Type: string
            Description: Expiration month.
            Example: "01"

          cc_year:
            Type: string
            Description: Expiration year.
            Example: "2025"

          default:
            Type: boolean
            Description: Is default payment method.
            Example: true

          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "19b3b92a-3399-4a3f-a121-afbd89a75d22",
    "cc_mask": "4111-1111-1111-1111",
    "cc_brand": "VISA",
    "cc_name": "Joe Doe",
    "cc_month": "01",
    "cc_year": "2025",
    "default": true,
    "address_1": "Guachipelín de Escazú",
    "address_2": "Ofibodegas #5",
    "city": "Escazú",
    "state": "San José",
    "zip": "10203"
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 404: error
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/payment-methods/{paymentMethodId}/default
Summary: Set a payment method as default
Operation ID: `1181ef9e8b8941b5c573bb696d43e626`

Description: Sets a payment method as the default for the authenticated user.

Security Requirements:
- bearerAuth

Parameters:
- `paymentMethodId` in path (Required): ID of the payment method to set as default
  Type: string

Responses:
- 200: Default payment method updated successfully
  Content-Type: `application/json`
  Schema:
    # Schema: PaymentMethodResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: PaymentMethod
        # Schema: PaymentMethod
        Type: object
        Properties:
          id:
            Type: string
            Description: Credit card id.
            Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

          cc_mask:
            Type: string
            Description: Masked credit card number.
            Example: "4111-1111-1111-1111"

          cc_brand:
            Type: string
            Description: Type of card.
            Example: "VISA"

          cc_name:
            Type: string
            Description: Name on the credit card.
            Example: "Joe Doe"

          cc_month:
            Type: string
            Description: Expiration month.
            Example: "01"

          cc_year:
            Type: string
            Description: Expiration year.
            Example: "2025"

          default:
            Type: boolean
            Description: Is default payment method.
            Example: true

          address_1:
            Type: string
            Description: Primary billing address line
            Example: "Guachipelín de Escazú"

          address_2:
            Type: string
            Description: Secondary billing address line
            Example: "Ofibodegas #5"

          city:
            Type: string
            Description: City
            Example: "Escazú"

          state:
            Type: string
            Description: State/Province
            Example: "San José"

          zip:
            Type: string
            Description: ZIP/Postal code
            Example: "10203"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "19b3b92a-3399-4a3f-a121-afbd89a75d22",
    "cc_mask": "4111-1111-1111-1111",
    "cc_brand": "VISA",
    "cc_name": "Joe Doe",
    "cc_month": "01",
    "cc_year": "2025",
    "default": true,
    "address_1": "Guachipelín de Escazú",
    "address_2": "Ofibodegas #5",
    "city": "Escazú",
    "state": "San José",
    "zip": "10203"
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 404: Not Found
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Plans

#### GET /api/plans/{planId}
Summary: Get plan details
Operation ID: `a10d5a128e1b0c094b61b6e6ec70bd26`

Description: Get details for a specific plan.

Security Requirements:
- bearerAuth

Parameters:
- `planId` in path (Required): Id of plan
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: Success
  Content-Type: `application/json`
  Schema:
    # Schema: PlanResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Plan
        # Schema: Plan
        Type: object
        Properties:
          id:
            Type: string
            Description: Plan ID.
            Example: "a3b8c9d0-1234-5678-9abc-def012345678"

          name:
            Type: string
            Description: Display name of the plan.
            Example: "Business"

          type:
            Type: enum
            Enum: ["free", "business", "enterprise"]
            Description: Type of the plan.
            Example: "enterprise"

          max_organizations:
            Type: integer
            Description: Maximum number of organizations allowed for this plan.
            Example: 3

          max_projects:
            Type: integer
            Description: Maximum number of projects allowed for this plan.
            Example: 10

          max_locales:
            Type: integer
            Description: Maximum number of locales allowed for this plan.
            Example: 5

          max_users:
            Type: integer
            Description: Maximum number of users allowed for this plan.
            Example: 25

          max_translator_users:
            Type: integer
            Description: Maximum number of translator users allowed for this plan.
            Example: 10

          price:
            Type: number
            Format: float
            Description: Monthly price for the plan. Null for Free and Enterprise plans.
            Example: 29



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "a3b8c9d0-1234-5678-9abc-def012345678",
    "name": "Business",
    "type": "enterprise",
    "max_organizations": 3,
    "max_projects": 10,
    "max_locales": 5,
    "max_users": 25,
    "max_translator_users": 10,
    "price": 29
  }
}
```
- 401: Unauthorized
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Project - Balance

#### GET /api/projects/{projectId}/credit/balance
Summary: Get Project Credit Balance
Operation ID: `3fd16868b0dfd4f689ab60022b23fa48`

Description: Get the credit balance of a project.

Security Requirements:
- bearerAuth

Parameters:
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransactionPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: BalanceTransaction
            Type: object
            Properties:
              id:
                Type: string
                Description: Transaction ID
                Example: "123e4567-e89b-12d3-a456-426614174001"

              entity_id:
                Type: string
                Description: Entity ID
                Example: "123e4567-e89b-12d3-a456-426614174000"

              entity_type:
                Type: string
                Description: Entity type (user, organization, project)
                Example: "user"

              amount:
                Type: number
                Format: float
                Description: Transaction amount
                Example: 50

              type:
                Type: enum
                Enum: ["credit", "auto_recharge", "machine_translation", "draw_from_account", "draw_from_organization", "prepaid_credits_invoiced", "free_credits_granted", "prepaid_credits_transfer", "free_credits_transfer"]
                Description: Transaction type
                Example: "prepaid_credits_transfer"

              balance_before:
                Type: number
                Format: float
                Description: Balance before transaction
                Example: 50

              balance_after:
                Type: number
                Format: float
                Description: Balance after transaction
                Example: 50

              prepaid_credit:
                Type: boolean
                Description: Pre-paid credit
                Example: true

              reference_entity_id:
                Type: string
                Description: Reference Entity ID
                Example: "ref_123"

              reference_entity_type:
                Type: string
                Description: Reference Entity Type
                Example: "user"

              payment_provider:
                Type: enum
                Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
                Description: Payment provider
                Example: "authorize_net"

              machine_translator:
                Type: enum
                Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                Description: Machine translator used in Transaction
                Example: "google"

              created_at:
                Type: integer
                Description: Transaction date
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174001",
      "entity_id": "123e4567-e89b-12d3-a456-426614174000",
      "entity_type": "user",
      "amount": 50,
      "type": "prepaid_credits_transfer",
      "balance_before": 50,
      "balance_after": 50,
      "prepaid_credit": true,
      "reference_entity_id": "ref_123",
      "reference_entity_type": "user",
      "payment_provider": "authorize_net",
      "machine_translator": "google",
      "created_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}/credit
Summary: Get Project Credit
Operation ID: `b593492760ff77fdb888f66b306e6327`

Description: Get the credit of a project.

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Balance
        # Schema: Balance
        Type: object
        Properties:
          total_balance:
            Type: number
            Format: float
            Description: Total balance.
            Example: 100

          prepaid_credits_balance:
            Type: number
            Format: float
            Description: Prepaid credits balance.
            Example: 50

          free_credits_balance:
            Type: number
            Format: float
            Description: Free credits balance.
            Example: 50



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_balance": 100,
    "prepaid_credits_balance": 50,
    "free_credits_balance": 50
  }
}
```

---

#### POST /api/projects/{projectId}/credit
Summary: Add Credit to Project
Operation ID: `fe5d2a88adfe3efc792d3172bf3e19fe`

Description: Add credit to a project's balance.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: AddCreditRequest
  Type: object
  Properties:
    amount (Required):
      Type: number
      Format: float
      Description: Amount of credit to add.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Balance
        # Schema: Balance
        Type: object
        Properties:
          total_balance:
            Type: number
            Format: float
            Description: Total balance.
            Example: 100

          prepaid_credits_balance:
            Type: number
            Format: float
            Description: Prepaid credits balance.
            Example: 50

          free_credits_balance:
            Type: number
            Format: float
            Description: Free credits balance.
            Example: 50



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_balance": 100,
    "prepaid_credits_balance": 50,
    "free_credits_balance": 50
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects/{projectId}/credit/recharge-card
Summary: Set Project Credit Card for Recharge
Operation ID: `85e02f28cf796a4067bc6c9107bb703e`

Description: Set a project's credit card for automatic recharge.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: SetRechargeCreditCardRequest
  Type: object
  Properties:
    cc_id (Required):
      Type: string
      Description: The credit card id for the payment
      Example: "f3115745-511e-460b-9813-1094a5099bbb"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects/{project}/credit/transfer/organization/{organization}
Summary: Transfer Credits from Project to Organization
Operation ID: `7597516436126f32ad2493e81d4121e4`

Security Requirements:
- bearerAuth

Parameters:
- `project` in path (Required): No description
  Type: string
- `organizationId` in path (Required): No description
  Type: integer

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TransferCreditRequest
  Type: object
  Properties:
    prepaid_credits:
      Type: number
      Format: float
      Description: Amount of prepaid credits to transfer.
      Example: 100

    free_credits:
      Type: number
      Format: float
      Description: Amount of free credits to transfer.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransferResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceTransfer
        # Schema: BalanceTransfer
        Type: object
        Properties:
          source_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the source entity after transfer

          destination_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the destination entity after transfer



  Example Response:
```json
{
  "status": true,
  "data": {
    "source_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    },
    "destination_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    }
  }
}
```
- 400: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 403: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}/credit/summary
Summary: Get Project Credit Usage Summary
Operation ID: `6a8725be1087a8a1cebeffa57760ee5d`

Description: Get the aggregated credit usage summary of a project, optionally filtered by date range.

Security Requirements:
- bearerAuth

Parameters:
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceSummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceSummary
        # Schema: BalanceSummary
        Type: object
        Properties:
          prepaid_credits_used:
            Type: number
            Format: float
            Description: Total prepaid credits used in the period
            Example: 1000

          prepaid_credits_available:
            Type: number
            Format: float
            Description: Total prepaid credits currently available
            Example: 2000

          free_credits_used:
            Type: number
            Format: float
            Description: Total free credits used in the period
            Example: 500

          free_credits_available:
            Type: number
            Format: float
            Description: Total free credits currently available
            Example: 1500

          total_credits_used:
            Type: number
            Format: float
            Description: Total credits used in the period
            Example: 1500

          total_credits_available:
            Type: number
            Format: float
            Description: Total credits currently available
            Example: 2000



  Example Response:
```json
{
  "status": true,
  "data": {
    "prepaid_credits_used": 1000,
    "prepaid_credits_available": 2000,
    "free_credits_used": 500,
    "free_credits_available": 1500,
    "total_credits_used": 1500,
    "total_credits_available": 2000
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Project

#### GET /api/projects
Summary: List User's Projects
Operation ID: `2ea4919d0a733012d5e30a9c60233aca`

Description: Get all projects a given user has access to. Super admins will view all projects. Pagination parameters are optional. If user locale is sent it will return project target locales as an object categorized by language and displayed in the language of {userLocale}; otherwise it will only return a plain array of locale codes. Results can be ordered by: title, description, organization_name, admin, last_activity_at, user_joined_at, created_at, updated_at, id.

Security Requirements:
- bearerAuth

Parameters:
- `visitor_locale` in query: Query parameter representing locale code to display the data in.
  Type: string
  Example: `"es-cr"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Project
            Type: object
            Properties:
              id:
                Type: string
                Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

              owner_id:
                Type: string
                Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

              title:
                Type: string
                Example: "Comercado"

              description:
                Type: string
                Example: "Translations for Comercado app"

              base_locale:
                Type: string
                Description: Locale in which project phrase strings are written.
                Example: "en-us"

              organization_id:
                Type: string
                Description: Id of organization the project belongs to
                Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

              organization_name:
                Type: string
                Example: "Konopelski, Ullrich and Wolf"

              target_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "fr-ca"

                Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

              default_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "es-cr"

                Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

              website_url:
                Type: string
                Example: "https://example.com"

              icon:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              logo:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              settings:
                Type: App\Data\ProjectSettingsData
                allOf:
                  # Schema: ProjectSettingsData
                  Type: object
                  Properties:
                    use_translation_memory:
                      Type: boolean
                      Default: true
                      Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                      Example: true

                    machine_translate_new_phrases:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                      Example: true

                    use_machine_translations:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                      Example: true

                    translate_base_locale_only:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                      Example: true

                    machine_translator:
                      Type: enum
                      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                      Default: "default"
                      Description: Project wide setting that determines which machine translator to use.
                      Example: "default"

                    broadcast_translations:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                      Example: true

                    monthly_credit_usage_limit:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the monthly usage limit for the project.
                      Example: 20

                    auto_recharge_enabled:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                      Example: true

                    auto_recharge_threshold:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the threshold for automatic recharge.
                      Example: 20

                    auto_recharge_amount:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the amount to recharge.
                      Example: 20

                    auto_recharge_source:
                      Type: enum
                      Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                      Default: "organization_balance_or_credit_card"
                      Description: Project wide setting that determines the source of the automatic recharge.
                      Example: "organization_balance_or_credit_card"



              admin:
                Type: boolean
                Example: true

              last_activity_at:
                Type: integer
                Example: 1764988634

              totals:
                Type: App\Data\TranslationTotals\GeneralProjectTotals
                allOf:
                  # Schema: GeneralProjectTotals
                  Type: object
                  Properties:
                    phrases:
                      Type: integer
                      Description: Total number of phrases in project.
                      Example: 291

                    words:
                      Type: integer
                      Description: Total number of words in project.
                      Example: 755

                    words_to_translate:
                      Type: integer
                      Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                      Example: 3020

                    target_locales:
                      Type: integer
                      Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                      Example: 4



              role:
                Type: App\Data\RoleData
                allOf:
                  # Schema: RoleData
                  Type: object
                  Properties:
                    value:
                      Type: string
                      Description: Role value
                      Example: "organization_admin"

                    label:
                      Type: string
                      Description: Role label
                      Example: "Organization Admin"



              user_joined_at:
                Type: integer
                Description: Timestamp when the user joined the project or when they got access to it
                Example: 1764988634

              created_at:
                Type: integer
                Example: 1764988634

              updated_at:
                Type: integer
                Example: 1764988634

              deleted_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
      "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
      "title": "Comercado",
      "description": "Translations for Comercado app",
      "base_locale": "en-us",
      "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
      "organization_name": "Konopelski, Ullrich and Wolf",
      "target_locales": [
        "fr-ca"
      ],
      "default_locales": [
        "es-cr"
      ],
      "website_url": "https://example.com",
      "icon": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "logo": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "settings": {
        "use_translation_memory": true,
        "machine_translate_new_phrases": true,
        "use_machine_translations": true,
        "translate_base_locale_only": true,
        "machine_translator": "default",
        "broadcast_translations": true,
        "monthly_credit_usage_limit": 20,
        "auto_recharge_enabled": true,
        "auto_recharge_threshold": 20,
        "auto_recharge_amount": 20,
        "auto_recharge_source": "organization_balance_or_credit_card"
      },
      "admin": true,
      "last_activity_at": 1764988634,
      "totals": {
        "phrases": 291,
        "words": 755,
        "words_to_translate": 3020,
        "target_locales": 4
      },
      "role": {
        "value": "organization_admin",
        "label": "Organization Admin"
      },
      "user_joined_at": 1764988634,
      "created_at": 1764988634,
      "updated_at": 1764988634,
      "deleted_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects
Summary: Create New Project
Operation ID: `48ad9efff74ef8cbc32998fcb386e258`

Description: Create a project in an organization.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ProjectRequest
  Type: object
  Properties:
    organization_id (Required):
      Type: string
      Example: "7bcd0875-d251-4eec-9fe8-89f07e95d3ca"

    title (Required):
      Type: string
      Example: "Comercado"

    base_locale (Required):
      Type: string
      Example: "en-us"

    description:
      Type: string
      Example: "Translations for Comercado app"

    target_locales:
      Type: array
      Items: 
        Type: string
        Example: "es-cr"

      Description: List of locales user can translate to

    website_url:
      Type: string
      Example: "https://example.com"

    icon:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"


      Description: Favicon of the project. If not provided, Langsys will attempt to fetch it from the website URL

    logo:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"


      Description: Logo of the project. If not provided, Langsys will attempt to fetch it from the website URL

    auto_recharge_credit_card_id:
      Type: string
      Example: "87e3ae2c-7329-415f-83cc-9c6e10b101e9"

    settings:
      Type: App\Data\ProjectSettingsData
      allOf:
        # Schema: ProjectSettingsData
        Type: object
        Properties:
          use_translation_memory:
            Type: boolean
            Default: true
            Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
            Example: true

          machine_translate_new_phrases:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
            Example: true

          use_machine_translations:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
            Example: true

          translate_base_locale_only:
            Type: boolean
            Default: false
            Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
            Example: true

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Project wide setting that determines which machine translator to use.
            Example: "default"

          broadcast_translations:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
            Example: true

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: Project wide setting that determines the monthly usage limit for the project.
            Example: 20

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: Project wide setting that determines the threshold for automatic recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: Project wide setting that determines the amount to recharge.
            Example: 20

          auto_recharge_source:
            Type: enum
            Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
            Default: "organization_balance_or_credit_card"
            Description: Project wide setting that determines the source of the automatic recharge.
            Example: "organization_balance_or_credit_card"





Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Project
        # Schema: Project
        Type: object
        Properties:
          id:
            Type: string
            Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

          owner_id:
            Type: string
            Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

          title:
            Type: string
            Example: "Comercado"

          description:
            Type: string
            Example: "Translations for Comercado app"

          base_locale:
            Type: string
            Description: Locale in which project phrase strings are written.
            Example: "en-us"

          organization_id:
            Type: string
            Description: Id of organization the project belongs to
            Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

          organization_name:
            Type: string
            Example: "Konopelski, Ullrich and Wolf"

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "fr-ca"

            Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

          default_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\ProjectSettingsData
            allOf:
              # Schema: ProjectSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Project wide setting that determines which machine translator to use.
                  Example: "default"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the monthly usage limit for the project.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                  Default: "organization_balance_or_credit_card"
                  Description: Project wide setting that determines the source of the automatic recharge.
                  Example: "organization_balance_or_credit_card"



          admin:
            Type: boolean
            Example: true

          last_activity_at:
            Type: integer
            Example: 1764988634

          totals:
            Type: App\Data\TranslationTotals\GeneralProjectTotals
            allOf:
              # Schema: GeneralProjectTotals
              Type: object
              Properties:
                phrases:
                  Type: integer
                  Description: Total number of phrases in project.
                  Example: 291

                words:
                  Type: integer
                  Description: Total number of words in project.
                  Example: 755

                words_to_translate:
                  Type: integer
                  Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                  Example: 3020

                target_locales:
                  Type: integer
                  Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                  Example: 4



          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          user_joined_at:
            Type: integer
            Description: Timestamp when the user joined the project or when they got access to it
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
    "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
    "title": "Comercado",
    "description": "Translations for Comercado app",
    "base_locale": "en-us",
    "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
    "organization_name": "Konopelski, Ullrich and Wolf",
    "target_locales": [
      "fr-ca"
    ],
    "default_locales": [
      "es-cr"
    ],
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "default",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_balance_or_credit_card"
    },
    "admin": true,
    "last_activity_at": 1764988634,
    "totals": {
      "phrases": 291,
      "words": 755,
      "words_to_translate": 3020,
      "target_locales": 4
    },
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}
Summary: Get Project Details
Operation ID: `ab1947f06eb856a75b4076270ef6ed9d`

Description: Get a single project. If user locale is sent it will return project target locales as an object categorized by language and displayed in the language of {userLocale}; otherwise it will only return a plain array of locale codes.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `visitor_locale` in query: Query parameter representing locale code to display the data in.
  Type: string
  Example: `"es-cr"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Project
        # Schema: Project
        Type: object
        Properties:
          id:
            Type: string
            Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

          owner_id:
            Type: string
            Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

          title:
            Type: string
            Example: "Comercado"

          description:
            Type: string
            Example: "Translations for Comercado app"

          base_locale:
            Type: string
            Description: Locale in which project phrase strings are written.
            Example: "en-us"

          organization_id:
            Type: string
            Description: Id of organization the project belongs to
            Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

          organization_name:
            Type: string
            Example: "Konopelski, Ullrich and Wolf"

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "fr-ca"

            Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

          default_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\ProjectSettingsData
            allOf:
              # Schema: ProjectSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Project wide setting that determines which machine translator to use.
                  Example: "default"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the monthly usage limit for the project.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                  Default: "organization_balance_or_credit_card"
                  Description: Project wide setting that determines the source of the automatic recharge.
                  Example: "organization_balance_or_credit_card"



          admin:
            Type: boolean
            Example: true

          last_activity_at:
            Type: integer
            Example: 1764988634

          totals:
            Type: App\Data\TranslationTotals\GeneralProjectTotals
            allOf:
              # Schema: GeneralProjectTotals
              Type: object
              Properties:
                phrases:
                  Type: integer
                  Description: Total number of phrases in project.
                  Example: 291

                words:
                  Type: integer
                  Description: Total number of words in project.
                  Example: 755

                words_to_translate:
                  Type: integer
                  Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                  Example: 3020

                target_locales:
                  Type: integer
                  Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                  Example: 4



          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          user_joined_at:
            Type: integer
            Description: Timestamp when the user joined the project or when they got access to it
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
    "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
    "title": "Comercado",
    "description": "Translations for Comercado app",
    "base_locale": "en-us",
    "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
    "organization_name": "Konopelski, Ullrich and Wolf",
    "target_locales": [
      "fr-ca"
    ],
    "default_locales": [
      "es-cr"
    ],
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "default",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_balance_or_credit_card"
    },
    "admin": true,
    "last_activity_at": 1764988634,
    "totals": {
      "phrases": 291,
      "words": 755,
      "words_to_translate": 3020,
      "target_locales": 4
    },
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/projects/{projectId}
Summary: Delete Project
Operation ID: `a8f94758a675b19c8261a049d7fc31f3`

Description: Delete a project. Optionally transfer all phrases and translations to another project before deletion.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: DeleteProjectRequest
  Type: object
  Properties:
    target_project_id:
      Type: string
      Description: Project ID to transfer phrases to before deletion. If not provided, phrases will be deleted.
      Example: "b5eeb20d-25b7-4f8e-bc93-53d054a516b6"

    include_translations:
      Type: boolean
      Default: false
      Description: Whether to also transfer translations.
      Example: true

    create_target_locales:
      Type: boolean
      Default: false
      Description: Whether to create target locales in target project if they do not exist. If false, only translations to existing locales will be transferred.
      Example: true

    force_transfer:
      Type: boolean
      Default: false
      Description: Force transfer even if projects base locale does not match.
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Project
        # Schema: Project
        Type: object
        Properties:
          id:
            Type: string
            Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

          owner_id:
            Type: string
            Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

          title:
            Type: string
            Example: "Comercado"

          description:
            Type: string
            Example: "Translations for Comercado app"

          base_locale:
            Type: string
            Description: Locale in which project phrase strings are written.
            Example: "en-us"

          organization_id:
            Type: string
            Description: Id of organization the project belongs to
            Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

          organization_name:
            Type: string
            Example: "Konopelski, Ullrich and Wolf"

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "fr-ca"

            Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

          default_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\ProjectSettingsData
            allOf:
              # Schema: ProjectSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Project wide setting that determines which machine translator to use.
                  Example: "default"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the monthly usage limit for the project.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                  Default: "organization_balance_or_credit_card"
                  Description: Project wide setting that determines the source of the automatic recharge.
                  Example: "organization_balance_or_credit_card"



          admin:
            Type: boolean
            Example: true

          last_activity_at:
            Type: integer
            Example: 1764988634

          totals:
            Type: App\Data\TranslationTotals\GeneralProjectTotals
            allOf:
              # Schema: GeneralProjectTotals
              Type: object
              Properties:
                phrases:
                  Type: integer
                  Description: Total number of phrases in project.
                  Example: 291

                words:
                  Type: integer
                  Description: Total number of words in project.
                  Example: 755

                words_to_translate:
                  Type: integer
                  Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                  Example: 3020

                target_locales:
                  Type: integer
                  Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                  Example: 4



          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          user_joined_at:
            Type: integer
            Description: Timestamp when the user joined the project or when they got access to it
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
    "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
    "title": "Comercado",
    "description": "Translations for Comercado app",
    "base_locale": "en-us",
    "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
    "organization_name": "Konopelski, Ullrich and Wolf",
    "target_locales": [
      "fr-ca"
    ],
    "default_locales": [
      "es-cr"
    ],
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "default",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_balance_or_credit_card"
    },
    "admin": true,
    "last_activity_at": 1764988634,
    "totals": {
      "phrases": 291,
      "words": 755,
      "words_to_translate": 3020,
      "target_locales": 4
    },
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/projects/{projectId}
Summary: Update Project
Operation ID: `7e328628a4994c4c231ff063be1e2469`

Description: Update a project.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ProjectUpdateRequest
  Type: object
  Properties:
    organization_id:
      Type: string
      Example: "2cb0b24e-e511-4b11-baa6-808e32240608"

    title:
      Type: string
      Example: "Comercado"

    base_locale:
      Type: string
      Description: Source locale for this project
      Example: "en-us"

    description:
      Type: string
      Example: "Translations for Comercado app"

    target_locales:
      Type: array
      Items: 
        Type: string
        Example: "es-cr"

      Description: List of locales user can translate to

    website_url:
      Type: string
      Example: "https://example.com"

    icon:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"



    logo:
      Type: App\Data\Photo
      allOf:
        # Schema: Photo
        Type: object
        Properties:
          id:
            Type: string
            Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

          path:
            Type: string
            Description: Local path of the photo.
            Example: "/public/images"

          provider:
            Type: enum
            Enum: ["gravatar", "imagekit", "custom"]
            Example: "imagekit"

          width:
            Type: integer
            Description: Width of the photo in pixels.
            Example: 445

          height:
            Type: integer
            Description: Height of the photo in pixels.
            Example: 214

          original:
            Type: string
            Description: Url of the original size of the photo
            Example: "https://example.com/original.jpg"

          medium:
            Type: string
            Description: Url of the medium size of the photo
            Example: "https://example.com/medium.jpg"

          thumb:
            Type: string
            Description: Url of the thumbnail size of the photo
            Example: "https://example.com/thumb.jpg"



    auto_recharge_credit_card_id:
      Type: string
      Example: "3554b087-e7f9-4d08-a3e7-3ad167a09136"

    settings:
      Type: App\Data\ProjectSettingsData
      allOf:
        # Schema: ProjectSettingsData
        Type: object
        Properties:
          use_translation_memory:
            Type: boolean
            Default: true
            Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
            Example: true

          machine_translate_new_phrases:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
            Example: true

          use_machine_translations:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
            Example: true

          translate_base_locale_only:
            Type: boolean
            Default: false
            Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
            Example: true

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Project wide setting that determines which machine translator to use.
            Example: "default"

          broadcast_translations:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
            Example: true

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: Project wide setting that determines the monthly usage limit for the project.
            Example: 20

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: Project wide setting that determines the threshold for automatic recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: Project wide setting that determines the amount to recharge.
            Example: 20

          auto_recharge_source:
            Type: enum
            Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
            Default: "organization_balance_or_credit_card"
            Description: Project wide setting that determines the source of the automatic recharge.
            Example: "organization_balance_or_credit_card"





Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Project
        # Schema: Project
        Type: object
        Properties:
          id:
            Type: string
            Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

          owner_id:
            Type: string
            Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

          title:
            Type: string
            Example: "Comercado"

          description:
            Type: string
            Example: "Translations for Comercado app"

          base_locale:
            Type: string
            Description: Locale in which project phrase strings are written.
            Example: "en-us"

          organization_id:
            Type: string
            Description: Id of organization the project belongs to
            Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

          organization_name:
            Type: string
            Example: "Konopelski, Ullrich and Wolf"

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "fr-ca"

            Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

          default_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\ProjectSettingsData
            allOf:
              # Schema: ProjectSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Project wide setting that determines which machine translator to use.
                  Example: "default"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the monthly usage limit for the project.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                  Default: "organization_balance_or_credit_card"
                  Description: Project wide setting that determines the source of the automatic recharge.
                  Example: "organization_balance_or_credit_card"



          admin:
            Type: boolean
            Example: true

          last_activity_at:
            Type: integer
            Example: 1764988634

          totals:
            Type: App\Data\TranslationTotals\GeneralProjectTotals
            allOf:
              # Schema: GeneralProjectTotals
              Type: object
              Properties:
                phrases:
                  Type: integer
                  Description: Total number of phrases in project.
                  Example: 291

                words:
                  Type: integer
                  Description: Total number of words in project.
                  Example: 755

                words_to_translate:
                  Type: integer
                  Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                  Example: 3020

                target_locales:
                  Type: integer
                  Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                  Example: 4



          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          user_joined_at:
            Type: integer
            Description: Timestamp when the user joined the project or when they got access to it
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
    "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
    "title": "Comercado",
    "description": "Translations for Comercado app",
    "base_locale": "en-us",
    "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
    "organization_name": "Konopelski, Ullrich and Wolf",
    "target_locales": [
      "fr-ca"
    ],
    "default_locales": [
      "es-cr"
    ],
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "default",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_balance_or_credit_card"
    },
    "admin": true,
    "last_activity_at": 1764988634,
    "totals": {
      "phrases": 291,
      "words": 755,
      "words_to_translate": 3020,
      "target_locales": 4
    },
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}/settings
Summary: Get Project Settings
Operation ID: `04a75e08fc38854ab636adaee1ef4b47`

Description: Get the override settings for a project. This will return the settings that are overridden for the project or an empty object if no settings are overridden for the project.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ProjectSettings
        # Schema: ProjectSettings
        Type: object
        Properties:
          use_translation_memory:
            Type: boolean
            Default: true
            Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
            Example: true

          machine_translate_new_phrases:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
            Example: true

          use_machine_translations:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
            Example: true

          translate_base_locale_only:
            Type: boolean
            Default: false
            Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
            Example: true

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Project wide setting that determines which machine translator to use.
            Example: "deepl"

          broadcast_translations:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
            Example: true

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: Project wide setting that determines the monthly usage limit for the project.
            Example: 20

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: Project wide setting that determines the threshold for automatic recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: Project wide setting that determines the amount to recharge.
            Example: 20

          auto_recharge_source:
            Type: enum
            Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
            Default: "organization_balance_or_credit_card"
            Description: Project wide setting that determines the source of the automatic recharge.
            Example: "organization_balance"



  Example Response:
```json
{
  "status": true,
  "data": {
    "use_translation_memory": true,
    "machine_translate_new_phrases": true,
    "use_machine_translations": true,
    "translate_base_locale_only": true,
    "machine_translator": "deepl",
    "broadcast_translations": true,
    "monthly_credit_usage_limit": 20,
    "auto_recharge_enabled": true,
    "auto_recharge_threshold": 20,
    "auto_recharge_amount": 20,
    "auto_recharge_source": "organization_balance"
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/projects/{projectId}/settings
Summary: Clear Project Settings
Operation ID: `5a2a8a299becfc579ef16bee92be883e`

Description: Clear the override settings for a project. This will clear the settings that are overridden for the project.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}/stats
Summary: Get Project Statistics
Operation ID: `c6917b81969d34b735d9b731f7dc1dd8`

Description: Get a detailed stats about a project's phrases and translations.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectTotalsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: ProjectTotals
        # Schema: ProjectTotals
        Type: object
        Properties:
          phrases:
            Type: integer
            Description: Total number of phrases in project.
            Example: 291

          words:
            Type: integer
            Description: Total number of words in project.
            Example: 755

          words_to_translate:
            Type: integer
            Description: Total number of words to translate in project. This is equivalent to words * target_locales.
            Example: 3020

          target_locales:
            Type: integer
            Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
            Example: 4

          translated_target_locales:
            Type: integer
            Description: Total number of locales for which translations exist.
            Example: 3

          translations:
            Type: App\Data\TranslationTotals\GlobalTranslationTotals
            allOf:
              # Schema: GlobalTranslationTotals
              Type: object
              Properties:
                total:
                  Type: integer
                  Description: Total number of translations in project.
                  Example: 712

                human:
                  Type: integer
                  Description: Total number of human translations in project.
                  Example: 618

                ai:
                  Type: integer
                  Description: Total number of ai translations in project.
                  Example: 94

                words:
                  Type: App\Data\TranslationTotals\WordTranslationTotals
                  allOf:
                    # Schema: WordTranslationTotals
                    Type: object
                    Properties:
                      total:
                        Type: integer
                        Description: Total number of words translated in project.
                        Example: 803

                      human:
                        Type: integer
                        Description: Total number of human translations in project.
                        Example: 778

                      ai:
                        Type: integer
                        Description: Total number of ai translations in project.
                        Example: 25



                locales:
                  Type: array
                  Items: 
                    allOf:
                      # Schema: LocaleTranslationTotals
                      Type: object
                      Properties:
                        total:
                          Type: integer
                          Description: Total number of translations in locale.
                          Example: 270

                        human:
                          Type: integer
                          Description: Total number of human translations in locale.
                          Example: 250

                        ai:
                          Type: integer
                          Description: Total number of ai translations in locale.
                          Example: 20

                        locale:
                          Type: string
                          Description: Locale code.
                          Example: "es-cr"

                        words:
                          Type: App\Data\TranslationTotals\WordTranslationTotals
                          allOf:
                            # Schema: WordTranslationTotals
                            Type: object
                            Properties:
                              total:
                                Type: integer
                                Description: Total number of words translated in project.
                                Example: 803

                              human:
                                Type: integer
                                Description: Total number of human translations in project.
                                Example: 778

                              ai:
                                Type: integer
                                Description: Total number of ai translations in project.
                                Example: 25







            Description: Translations totals further separated into human vs ai, and also grouped by locale.

          my_translations:
            Type: App\Data\TranslationTotals\TotalsWithLocales
            allOf:
              # Schema: TotalsWithLocales
              Type: object
              Properties:
                total:
                  Type: integer
                  Example: 311

                words:
                  Type: integer
                  Example: 801

                locales:
                  Type: array
                  Items: 
                    allOf:
                      # Schema: LocaleTotals
                      Type: object
                      Properties:
                        total:
                          Type: integer
                          Example: 27

                        words:
                          Type: integer
                          Example: 72

                        locale:
                          Type: string
                          Example: "es-cr"





            Description: Translations made by user in the project and also grouped by locale.

          untranslated:
            Type: App\Data\TranslationTotals\TotalsWithLocales
            allOf:
              # Schema: TotalsWithLocales
              Type: object
              Properties:
                total:
                  Type: integer
                  Example: 311

                words:
                  Type: integer
                  Example: 801

                locales:
                  Type: array
                  Items: 
                    allOf:
                      # Schema: LocaleTotals
                      Type: object
                      Properties:
                        total:
                          Type: integer
                          Example: 27

                        words:
                          Type: integer
                          Example: 72

                        locale:
                          Type: string
                          Example: "es-cr"





            Description: Untranslated phrases in project and also grouped by locale.



  Example Response:
```json
{
  "status": true,
  "data": {
    "phrases": 291,
    "words": 755,
    "words_to_translate": 3020,
    "target_locales": 4,
    "translated_target_locales": 3,
    "translations": {
      "total": 712,
      "human": 618,
      "ai": 94,
      "words": {
        "total": 803,
        "human": 778,
        "ai": 25
      },
      "locales": [
        {
          "total": 270,
          "human": 250,
          "ai": 20,
          "locale": "es-cr",
          "words": {
            "total": 803,
            "human": 778,
            "ai": 25
          }
        }
      ]
    },
    "my_translations": {
      "total": 311,
      "words": 801,
      "locales": [
        {
          "total": 27,
          "words": 72,
          "locale": "es-cr"
        }
      ]
    },
    "untranslated": {
      "total": 311,
      "words": 801,
      "locales": [
        {
          "total": 27,
          "words": 72,
          "locale": "es-cr"
        }
      ]
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/projects/{projectId}/default
Summary: Set Default Language Locale
Operation ID: `fd3cc1de130b144e5ce651bd8942a8c3`

Description: Set locale code as default project target locale for that language. This will unset any previous default and will add the locale in case it didn't exist as a project target locale.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: SingleLocaleRequest
  Type: object
  Properties:
    locale (Required):
      Type: string
      Description: Translation locale.
      Example: "es-es"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Project
        # Schema: Project
        Type: object
        Properties:
          id:
            Type: string
            Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

          owner_id:
            Type: string
            Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

          title:
            Type: string
            Example: "Comercado"

          description:
            Type: string
            Example: "Translations for Comercado app"

          base_locale:
            Type: string
            Description: Locale in which project phrase strings are written.
            Example: "en-us"

          organization_id:
            Type: string
            Description: Id of organization the project belongs to
            Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

          organization_name:
            Type: string
            Example: "Konopelski, Ullrich and Wolf"

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "fr-ca"

            Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

          default_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

          website_url:
            Type: string
            Example: "https://example.com"

          icon:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          logo:
            Type: App\Data\Photo
            allOf:
              # Schema: Photo
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                path:
                  Type: string
                  Description: Local path of the photo.
                  Example: "/public/images"

                provider:
                  Type: enum
                  Enum: ["gravatar", "imagekit", "custom"]
                  Example: "imagekit"

                width:
                  Type: integer
                  Description: Width of the photo in pixels.
                  Example: 445

                height:
                  Type: integer
                  Description: Height of the photo in pixels.
                  Example: 214

                original:
                  Type: string
                  Description: Url of the original size of the photo
                  Example: "https://example.com/original.jpg"

                medium:
                  Type: string
                  Description: Url of the medium size of the photo
                  Example: "https://example.com/medium.jpg"

                thumb:
                  Type: string
                  Description: Url of the thumbnail size of the photo
                  Example: "https://example.com/thumb.jpg"



          settings:
            Type: App\Data\ProjectSettingsData
            allOf:
              # Schema: ProjectSettingsData
              Type: object
              Properties:
                use_translation_memory:
                  Type: boolean
                  Default: true
                  Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                  Example: true

                machine_translate_new_phrases:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                  Example: true

                use_machine_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                  Example: true

                translate_base_locale_only:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                  Example: true

                machine_translator:
                  Type: enum
                  Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                  Default: "default"
                  Description: Project wide setting that determines which machine translator to use.
                  Example: "default"

                broadcast_translations:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                  Example: true

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the monthly usage limit for the project.
                  Example: 20

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the threshold for automatic recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: Project wide setting that determines the amount to recharge.
                  Example: 20

                auto_recharge_source:
                  Type: enum
                  Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                  Default: "organization_balance_or_credit_card"
                  Description: Project wide setting that determines the source of the automatic recharge.
                  Example: "organization_balance_or_credit_card"



          admin:
            Type: boolean
            Example: true

          last_activity_at:
            Type: integer
            Example: 1764988634

          totals:
            Type: App\Data\TranslationTotals\GeneralProjectTotals
            allOf:
              # Schema: GeneralProjectTotals
              Type: object
              Properties:
                phrases:
                  Type: integer
                  Description: Total number of phrases in project.
                  Example: 291

                words:
                  Type: integer
                  Description: Total number of words in project.
                  Example: 755

                words_to_translate:
                  Type: integer
                  Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                  Example: 3020

                target_locales:
                  Type: integer
                  Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                  Example: 4



          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          user_joined_at:
            Type: integer
            Description: Timestamp when the user joined the project or when they got access to it
            Example: 1764988634

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
    "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
    "title": "Comercado",
    "description": "Translations for Comercado app",
    "base_locale": "en-us",
    "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
    "organization_name": "Konopelski, Ullrich and Wolf",
    "target_locales": [
      "fr-ca"
    ],
    "default_locales": [
      "es-cr"
    ],
    "website_url": "https://example.com",
    "icon": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "logo": {
      "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
      "path": "/public/images",
      "provider": "imagekit",
      "width": 445,
      "height": 214,
      "original": "https://example.com/original.jpg",
      "medium": "https://example.com/medium.jpg",
      "thumb": "https://example.com/thumb.jpg"
    },
    "settings": {
      "use_translation_memory": true,
      "machine_translate_new_phrases": true,
      "use_machine_translations": true,
      "translate_base_locale_only": true,
      "machine_translator": "default",
      "broadcast_translations": true,
      "monthly_credit_usage_limit": 20,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "auto_recharge_source": "organization_balance_or_credit_card"
    },
    "admin": true,
    "last_activity_at": 1764988634,
    "totals": {
      "phrases": 291,
      "words": 755,
      "words_to_translate": 3020,
      "target_locales": 4
    },
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "user_joined_at": 1764988634,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects/{projectId}/transfer-ownership
Summary: Transfer Project Ownership
Operation ID: `a4efc944cb2c76a853000b3667d02df6`

Description: Transfer ownership of the project to a new user. Only available for project owner or organization admins.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: UserInvitationRequest
  Type: object
  Properties:
    user_id:
      Type: string
      Description: The id of the user to invite. If this field is provided, then the email field will be ignored.
      Example: "44b341b1-1946-45ff-a48c-eb33065cb8c3"

    email:
      Type: string
      Description: This field should be provided if the userId is not provided. It should be used for users who are not Langsys users. If the email provided is for an existing user then this will behave in the same way as sending the userId.
      Example: "mbatz@tromp.com"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Project - Invitations

#### GET /api/projects/{projectId}/invitations
Summary: List Project Invitations
Operation ID: `69d7036e8bd69a03924e2b61933ff11d`

Description: Get all sent invitations for a project. Only available for project admins. 

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Invitation
            Type: object
            Properties:
              id:
                Type: string
                Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

              inviter_id:
                Type: string
                Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

              inviter:
                Type: string
                Example: "John Doe"

              invitee_id:
                Type: string
                Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

              invitee:
                Type: string
                Example: "John Miles"

              email:
                Type: string
                Example: "clement.terry@hotmail.com"

              entity_id:
                Type: string
                Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

              entity_type:
                Type: string
                Example: "Organization"

              entity_name:
                Type: string
                Example: "Flexmark"

              role:
                Type: string
                Example: "organization_admin"

              expires_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
      "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
      "inviter": "John Doe",
      "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
      "invitee": "John Miles",
      "email": "clement.terry@hotmail.com",
      "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
      "entity_type": "Organization",
      "entity_name": "Flexmark",
      "role": "organization_admin",
      "expires_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects/{projectId}/invitations
Summary: Invite User to Project
Operation ID: `eeada08b8ca4d12d24a6400f202e7e4a`

Description: Invite a user to a project.<br><br><i>**This function will send an invitation email to user.</i>

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ProjectInvitationRequest
  Type: object
  Properties:
    user_id:
      Type: string
      Description: The id of the user to invite. If this field is provided, then the email field will be ignored.
      Example: "3b1f18d7-81ff-4b70-ae34-af7bc6a28ba8"

    email:
      Type: string
      Description: The email of the user to invite. Should be provided if the user is not part of the Langsys platform yet. If the email provided is for an existing user then this will behave in the same way as sending that user_id in the request
      Example: "heath.flatley@hotmail.com"

    role:
      Type: enum
      Enum: ["project_admin", "project_user", "translator"]
      Default: "project_user"
      Example: "project_user"

    target_locales:
      Type: array
      Items: 
        Type: string
        Example: "ee_GH"

      Description: List of locales translator can translate to. Only send this if role is of type translator.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Invitation
        # Schema: Invitation
        Type: object
        Properties:
          id:
            Type: string
            Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

          inviter_id:
            Type: string
            Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

          inviter:
            Type: string
            Example: "John Doe"

          invitee_id:
            Type: string
            Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

          invitee:
            Type: string
            Example: "John Miles"

          email:
            Type: string
            Example: "clement.terry@hotmail.com"

          entity_id:
            Type: string
            Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

          entity_type:
            Type: string
            Example: "Organization"

          entity_name:
            Type: string
            Example: "Flexmark"

          role:
            Type: string
            Example: "organization_admin"

          expires_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
    "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
    "inviter": "John Doe",
    "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
    "invitee": "John Miles",
    "email": "clement.terry@hotmail.com",
    "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
    "entity_type": "Organization",
    "entity_name": "Flexmark",
    "role": "organization_admin",
    "expires_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/invitations/all
Summary: List All Project Invitations
Operation ID: `69b0c01dda7a165fb0d9c79929258233`

Description: Retrieve all project invitations that have been sent where the logged user holds an admin role.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Invitation
            Type: object
            Properties:
              id:
                Type: string
                Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

              inviter_id:
                Type: string
                Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

              inviter:
                Type: string
                Example: "John Doe"

              invitee_id:
                Type: string
                Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

              invitee:
                Type: string
                Example: "John Miles"

              email:
                Type: string
                Example: "clement.terry@hotmail.com"

              entity_id:
                Type: string
                Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

              entity_type:
                Type: string
                Example: "Organization"

              entity_name:
                Type: string
                Example: "Flexmark"

              role:
                Type: string
                Example: "organization_admin"

              expires_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
      "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
      "inviter": "John Doe",
      "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
      "invitee": "John Miles",
      "email": "clement.terry@hotmail.com",
      "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
      "entity_type": "Organization",
      "entity_name": "Flexmark",
      "role": "organization_admin",
      "expires_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Project - Reports

#### GET /api/projects/{projectId}/reports/machine-translation-transactions
Summary: Get Project Translation Transactions
Operation ID: `d53c183122173d14611357cc88833b37`

Description: Get machine translation transactions for a specific project. Optionally filter by user ID, locale code, machine translator and date range.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: MachineTranslationTransactionResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: MachineTranslationTransaction
        # Schema: MachineTranslationTransaction
        Type: object
        Properties:
          id:
            Type: string
            Example: "ba98bde6-3552-454d-903b-6907795f48e4"

          phrase_id:
            Type: string
            Description: Phrase ID.
            Example: "2c558493-3b75-44fb-952d-bcb40b177a45"

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Description: Machine translation service.
            Example: "deepl"

          transaction_type:
            Type: enum
            Enum: ["translation", "detection"]
            Description: Machine translation transaction type.
            Example: "detection"

          user_id:
            Type: string
            Description: User that executed the machine translation.
            Example: "1f081808-cb7a-49be-bd2c-ab6b96e43c39"

          locale_code:
            Type: string
            Description: Machine translation locale.
            Example: "es-es"

          phrases:
            Type: integer
            Description: Total number of phrases translated in transaction.
            Example: 13

          words:
            Type: integer
            Description: Total number of words translated in transaction.
            Example: 152

          billing_amount:
            Type: number
            Format: float
            Description: Total billing amount for transaction.
            Example: 152

          date:
            Type: integer
            Description: Machine translation transaction date.
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "ba98bde6-3552-454d-903b-6907795f48e4",
    "phrase_id": "2c558493-3b75-44fb-952d-bcb40b177a45",
    "machine_translator": "deepl",
    "transaction_type": "detection",
    "user_id": "1f081808-cb7a-49be-bd2c-ab6b96e43c39",
    "locale_code": "es-es",
    "phrases": 13,
    "words": 152,
    "billing_amount": 152,
    "date": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}/reports/machine-translation-transactions-summary
Summary: Get Project Translation Summary
Operation ID: `e3b37ff0215404cb158da7b9f1ca6a32`

Description: Get machine translation transactions summary for a specific project. Optionally filter by date range.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: DateRangeRequest
  Type: object
  Properties:
    start_date:
      Type: string
      Description: Start date to filter by
      Example: "2024-01-01"

    end_date:
      Type: string
      Description: End date to filter by
      Example: "2024-01-31"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: MachineTranslationSummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: MachineTranslationSummary
        # Schema: MachineTranslationSummary
        Type: object
        Properties:
          total_phrases:
            Type: integer
            Description: Total number of phrases translated.
            Example: 130

          total_words:
            Type: integer
            Description: Total number of words translated.
            Example: 1520

          billing_amount:
            Type: number
            Format: float
            Description: Total billing amount of translations.
            Example: 11520



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_phrases": 130,
    "total_words": 1520,
    "billing_amount": 11520
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Project - Trash

#### GET /api/projects/trash
Summary: View deleted projects
Operation ID: `832dfb0a53a3f6c2c7dc3a305d16acca`

Description: List deleted projects within the last 45 days. Optionally filter by organization. If no organization is provided, all deleted projects from organizations where the user holds an admin role will be returned.

Security Requirements:
- bearerAuth

Parameters:
- `organization_id` in query: Filter results by organization ID
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ProjectPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Project
            Type: object
            Properties:
              id:
                Type: string
                Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

              owner_id:
                Type: string
                Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

              title:
                Type: string
                Example: "Comercado"

              description:
                Type: string
                Example: "Translations for Comercado app"

              base_locale:
                Type: string
                Description: Locale in which project phrase strings are written.
                Example: "en-us"

              organization_id:
                Type: string
                Description: Id of organization the project belongs to
                Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

              organization_name:
                Type: string
                Example: "Konopelski, Ullrich and Wolf"

              target_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "fr-ca"

                Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

              default_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "es-cr"

                Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

              website_url:
                Type: string
                Example: "https://example.com"

              icon:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              logo:
                Type: App\Data\Photo
                allOf:
                  # Schema: Photo
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

                    path:
                      Type: string
                      Description: Local path of the photo.
                      Example: "/public/images"

                    provider:
                      Type: enum
                      Enum: ["gravatar", "imagekit", "custom"]
                      Example: "imagekit"

                    width:
                      Type: integer
                      Description: Width of the photo in pixels.
                      Example: 445

                    height:
                      Type: integer
                      Description: Height of the photo in pixels.
                      Example: 214

                    original:
                      Type: string
                      Description: Url of the original size of the photo
                      Example: "https://example.com/original.jpg"

                    medium:
                      Type: string
                      Description: Url of the medium size of the photo
                      Example: "https://example.com/medium.jpg"

                    thumb:
                      Type: string
                      Description: Url of the thumbnail size of the photo
                      Example: "https://example.com/thumb.jpg"



              settings:
                Type: App\Data\ProjectSettingsData
                allOf:
                  # Schema: ProjectSettingsData
                  Type: object
                  Properties:
                    use_translation_memory:
                      Type: boolean
                      Default: true
                      Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
                      Example: true

                    machine_translate_new_phrases:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
                      Example: true

                    use_machine_translations:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
                      Example: true

                    translate_base_locale_only:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
                      Example: true

                    machine_translator:
                      Type: enum
                      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                      Default: "default"
                      Description: Project wide setting that determines which machine translator to use.
                      Example: "default"

                    broadcast_translations:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
                      Example: true

                    monthly_credit_usage_limit:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the monthly usage limit for the project.
                      Example: 20

                    auto_recharge_enabled:
                      Type: boolean
                      Default: false
                      Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
                      Example: true

                    auto_recharge_threshold:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the threshold for automatic recharge.
                      Example: 20

                    auto_recharge_amount:
                      Type: number
                      Format: float
                      Description: Project wide setting that determines the amount to recharge.
                      Example: 20

                    auto_recharge_source:
                      Type: enum
                      Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
                      Default: "organization_balance_or_credit_card"
                      Description: Project wide setting that determines the source of the automatic recharge.
                      Example: "organization_balance_or_credit_card"



              admin:
                Type: boolean
                Example: true

              last_activity_at:
                Type: integer
                Example: 1764988634

              totals:
                Type: App\Data\TranslationTotals\GeneralProjectTotals
                allOf:
                  # Schema: GeneralProjectTotals
                  Type: object
                  Properties:
                    phrases:
                      Type: integer
                      Description: Total number of phrases in project.
                      Example: 291

                    words:
                      Type: integer
                      Description: Total number of words in project.
                      Example: 755

                    words_to_translate:
                      Type: integer
                      Description: Total number of words to translate in project. This is equivalent to words * target_locales.
                      Example: 3020

                    target_locales:
                      Type: integer
                      Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
                      Example: 4



              role:
                Type: App\Data\RoleData
                allOf:
                  # Schema: RoleData
                  Type: object
                  Properties:
                    value:
                      Type: string
                      Description: Role value
                      Example: "organization_admin"

                    label:
                      Type: string
                      Description: Role label
                      Example: "Organization Admin"



              user_joined_at:
                Type: integer
                Description: Timestamp when the user joined the project or when they got access to it
                Example: 1764988634

              created_at:
                Type: integer
                Example: 1764988634

              updated_at:
                Type: integer
                Example: 1764988634

              deleted_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "ce4ec6cd-1ed5-4764-969d-659c5185948d",
      "owner_id": "0f9eea73-cfa8-473d-844e-d60a9aaca68c",
      "title": "Comercado",
      "description": "Translations for Comercado app",
      "base_locale": "en-us",
      "organization_id": "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342",
      "organization_name": "Konopelski, Ullrich and Wolf",
      "target_locales": [
        "fr-ca"
      ],
      "default_locales": [
        "es-cr"
      ],
      "website_url": "https://example.com",
      "icon": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "logo": {
        "id": "eafe28eb-0886-4c82-92bc-9a4bb5a6b359",
        "path": "/public/images",
        "provider": "imagekit",
        "width": 445,
        "height": 214,
        "original": "https://example.com/original.jpg",
        "medium": "https://example.com/medium.jpg",
        "thumb": "https://example.com/thumb.jpg"
      },
      "settings": {
        "use_translation_memory": true,
        "machine_translate_new_phrases": true,
        "use_machine_translations": true,
        "translate_base_locale_only": true,
        "machine_translator": "default",
        "broadcast_translations": true,
        "monthly_credit_usage_limit": 20,
        "auto_recharge_enabled": true,
        "auto_recharge_threshold": 20,
        "auto_recharge_amount": 20,
        "auto_recharge_source": "organization_balance_or_credit_card"
      },
      "admin": true,
      "last_activity_at": 1764988634,
      "totals": {
        "phrases": 291,
        "words": 755,
        "words_to_translate": 3020,
        "target_locales": 4
      },
      "role": {
        "value": "organization_admin",
        "label": "Organization Admin"
      },
      "user_joined_at": 1764988634,
      "created_at": 1764988634,
      "updated_at": 1764988634,
      "deleted_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects/restore
Summary: Restore deleted projects
Operation ID: `8733882f69ce0d8b8d7983f1e9a877a9`

Description: Restore projects that have been deleted within the last 45 days. If the user does not have permission to restore one or more of the projects in the list, they will simply be excluded and not restored.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: RestoreRequest
  Type: object
  Properties:
    restoreable_ids (Required):
      Type: array
      Items: 
        Type: string
        Example: "977a3009-a7a6-4876-a893-acfa4bd92fc3"




Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Project - Users

#### GET /api/projects/{projectId}/users
Summary: Get Project Users
Operation ID: `2b45509bc4a44538267afc6f114a5175`

Description: Get a list of users who have access to this project. Pagination parameters are optional.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserProjectAccessPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: UserProjectAccess
            Type: object
            Properties:
              id:
                Type: string
                Example: "b40f4aa6-66b4-4751-9665-838e1d9e34a4"

              firstname:
                Type: string
                Example: "Jordane"

              lastname:
                Type: string
                Example: "Walter"

              email:
                Type: string
                Example: "lind.kristofer@hotmail.com"

              avatar:
                Type: App\Data\Avatar
                allOf:
                  # Schema: Avatar
                  Type: object
                  Properties:
                    width:
                      Type: integer
                      Example: 481

                    height:
                      Type: integer
                      Example: 396

                    original_url:
                      Type: string
                      Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                    thumb_url:
                      Type: string
                      Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                    medium_url:
                      Type: string
                      Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                    id:
                      Type: string
                      Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                    path:
                      Type: string
                      Description: Path of local file
                      Example: "/public/images"



              last_activity_at:
                Type: integer
                Example: 1764988634

              role:
                Type: App\Data\RoleData
                allOf:
                  # Schema: RoleData
                  Type: object
                  Properties:
                    value:
                      Type: string
                      Description: Role value
                      Example: "organization_admin"

                    label:
                      Type: string
                      Description: Role label
                      Example: "Organization Admin"



              invited_by:
                Type: App\Data\UserData
                allOf:
                  # Schema: UserData
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

                    firstname:
                      Type: string
                      Example: "Modesto"

                    lastname:
                      Type: string
                      Example: "Green"

                    email:
                      Type: string
                      Example: "schaden.laron@gmail.com"

                    phone:
                      Type: string
                      Example: "(401) 259-3149"

                    locale:
                      Type: string
                      Example: "kk_KZ"

                    last_seen_at:
                      Type: integer
                      Example: 1764988634

                    avatar:
                      Type: App\Data\Avatar
                      allOf:
                        # Schema: Avatar
                        Type: object
                        Properties:
                          width:
                            Type: integer
                            Example: 481

                          height:
                            Type: integer
                            Example: 396

                          original_url:
                            Type: string
                            Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                          thumb_url:
                            Type: string
                            Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                          medium_url:
                            Type: string
                            Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                          id:
                            Type: string
                            Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                          path:
                            Type: string
                            Description: Path of local file
                            Example: "/public/images"


                      Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found



              target_locales:
                Type: array
                Items: 
                  Type: string
                  Example: "es-cr"

                Description: List of locales user can translate to



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "b40f4aa6-66b4-4751-9665-838e1d9e34a4",
      "firstname": "Jordane",
      "lastname": "Walter",
      "email": "lind.kristofer@hotmail.com",
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      },
      "last_activity_at": 1764988634,
      "role": {
        "value": "organization_admin",
        "label": "Organization Admin"
      },
      "invited_by": {
        "id": "dd3fab24-3954-407f-ac04-7e590ca5f632",
        "firstname": "Modesto",
        "lastname": "Green",
        "email": "schaden.laron@gmail.com",
        "phone": "(401) 259-3149",
        "locale": "kk_KZ",
        "last_seen_at": 1764988634,
        "avatar": {
          "width": 481,
          "height": 396,
          "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
          "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
          "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
          "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
          "path": "/public/images"
        }
      },
      "target_locales": [
        "es-cr"
      ]
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/projects/{projectId}/users/settings
Summary: Get User Project Settings
Operation ID: `73e88f9f0c40f1aad48867290c908f27`

Description: Get overridden user settings for a project. Will return an empty object if no settings are overridden for the user in the project.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserProjectSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserProjectSettings
        # Schema: UserProjectSettings
        Type: object
        Properties:
          notifications:
            Type: App\Data\UserNotificationSettings
            allOf:
              # Schema: UserNotificationSettings
              Type: object
              Properties:
                new_phrase:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                invitation:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                added_to_entity:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


            Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



  Example Response:
```json
{
  "status": true,
  "data": {
    "notifications": {
      "new_phrase": [
        "broadcast"
      ],
      "invitation": [
        "broadcast"
      ],
      "added_to_entity": [
        "broadcast"
      ]
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/projects/{projectId}/users/settings
Summary: Clear User Project Settings
Operation ID: `47390b2162f7b787031a743a1897194c`

Description: Clear overridden user settings for a project. Will do nothing if no overriden settings exist.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/projects/{projectId}/users/settings
Summary: Update User Project Settings
Operation ID: `284b0009f79aace41dbdf7c923593091`

Description:  Update authenticated user settings for a project.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: UserProjectSettingsRequest
  Type: object
  Properties:
    notifications:
      Type: App\Data\UserNotificationSettings
      allOf:
        # Schema: UserNotificationSettings
        Type: object
        Properties:
          new_phrase:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

          invitation:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

          added_to_entity:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


      Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserSettings
        # Schema: UserSettings
        Type: object
        Properties:
          notifications:
            Type: App\Data\UserNotificationSettings
            allOf:
              # Schema: UserNotificationSettings
              Type: object
              Properties:
                new_phrase:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                invitation:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                added_to_entity:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


            Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: The maximum amount that can be drawn from the monthly balance of the user.
            Example: 100

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Whether auto recharge is enabled for the user
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
            Example: 20

          allow_draw_organizations:
            Type: boolean
            Default: true
            Description: The allow draw organizations for the user
            Example: true

          draw_organizations_limit_monthly:
            Type: number
            Format: float
            Description: The draw organizations limit monthly for the user
            Example: 100



  Example Response:
```json
{
  "status": true,
  "data": {
    "notifications": {
      "new_phrase": [
        "broadcast"
      ],
      "invitation": [
        "broadcast"
      ],
      "added_to_entity": [
        "broadcast"
      ]
    },
    "monthly_credit_usage_limit": 100,
    "auto_recharge_enabled": true,
    "auto_recharge_threshold": 20,
    "auto_recharge_amount": 20,
    "allow_draw_organizations": true,
    "draw_organizations_limit_monthly": 100
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/projects/{projectId}/users/{userId}
Summary: Add User to Project
Operation ID: `84a3c6dbcd0e06d90e59a6402abc3d08`

Description: Add a user to a project. This endpoint activates the user immediately. Only sends a notification email, it does not follow the invitation workflow. User must be part of the organization in order to be added directly to a project.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ProjectUserRequest
  Type: object
  Properties:
    role:
      Type: enum
      Enum: ["project_admin", "project_user", "translator"]
      Example: "translator"

    target_locales:
      Type: array
      Items: 
        Type: string
        Example: "sh_YU"

      Description: List of locales translator can translate to. Only send this if role is of type translator.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserProjectAccessResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserProjectAccess
        # Schema: UserProjectAccess
        Type: object
        Properties:
          id:
            Type: string
            Example: "b40f4aa6-66b4-4751-9665-838e1d9e34a4"

          firstname:
            Type: string
            Example: "Jordane"

          lastname:
            Type: string
            Example: "Walter"

          email:
            Type: string
            Example: "lind.kristofer@hotmail.com"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          last_activity_at:
            Type: integer
            Example: 1764988634

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          invited_by:
            Type: App\Data\UserData
            allOf:
              # Schema: UserData
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

                firstname:
                  Type: string
                  Example: "Modesto"

                lastname:
                  Type: string
                  Example: "Green"

                email:
                  Type: string
                  Example: "schaden.laron@gmail.com"

                phone:
                  Type: string
                  Example: "(401) 259-3149"

                locale:
                  Type: string
                  Example: "kk_KZ"

                last_seen_at:
                  Type: integer
                  Example: 1764988634

                avatar:
                  Type: App\Data\Avatar
                  allOf:
                    # Schema: Avatar
                    Type: object
                    Properties:
                      width:
                        Type: integer
                        Example: 481

                      height:
                        Type: integer
                        Example: 396

                      original_url:
                        Type: string
                        Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                      thumb_url:
                        Type: string
                        Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                      medium_url:
                        Type: string
                        Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                      id:
                        Type: string
                        Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                      path:
                        Type: string
                        Description: Path of local file
                        Example: "/public/images"


                  Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found



          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: List of locales user can translate to



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "b40f4aa6-66b4-4751-9665-838e1d9e34a4",
    "firstname": "Jordane",
    "lastname": "Walter",
    "email": "lind.kristofer@hotmail.com",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "last_activity_at": 1764988634,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "invited_by": {
      "id": "dd3fab24-3954-407f-ac04-7e590ca5f632",
      "firstname": "Modesto",
      "lastname": "Green",
      "email": "schaden.laron@gmail.com",
      "phone": "(401) 259-3149",
      "locale": "kk_KZ",
      "last_seen_at": 1764988634,
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      }
    },
    "target_locales": [
      "es-cr"
    ]
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/projects/{projectId}/users/{userId}
Summary: Remove User From Project
Operation ID: `5e2c13f236b4d3e95e3c27c7aa60db86`

Description: Remove user from project. Only available for project admins. Only the project owner can remove an admin. If user to be removed is an organization admin, then the project will be added to the user's disabled projects.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserExtendedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserExtended
        # Schema: UserExtended
        Type: object
        Properties:
          id:
            Type: string
            Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

          firstname:
            Type: string
            Example: "Estelle"

          lastname:
            Type: string
            Example: "McLaughlin"

          email:
            Type: string
            Example: "schuppe.elmore@gmail.com"

          phone:
            Type: string
            Example: "(630) 622-5121"

          locale:
            Type: string
            Example: "es-cr"

          last_seen_at:
            Type: integer
            Description: Unix timestamp indicating last time the user interacted with the system.
            Example: 1764988634

          created_at:
            Type: integer
            Description: Unix timestamp indicating creation date.
            Example: 1764988634

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"


            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

          source_locales:
            Type: array
            Items: 
              Type: string
              Example: "en_MH"

            Description: List of locales user can translate from

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "ps_AF"

            Description: List of locales user can translate to

          settings:
            Type: App\Data\UserSettingsData
            allOf:
              # Schema: UserSettingsData
              Type: object
              Properties:
                notifications:
                  Type: App\Data\UserNotificationSettings
                  allOf:
                    # Schema: UserNotificationSettings
                    Type: object
                    Properties:
                      new_phrase:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      invitation:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      added_to_entity:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


                  Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: The maximum amount that can be drawn from the monthly balance of the user.
                  Example: 100

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Whether auto recharge is enabled for the user
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
                  Example: 20

                allow_draw_organizations:
                  Type: boolean
                  Default: true
                  Description: The allow draw organizations for the user
                  Example: true

                draw_organizations_limit_monthly:
                  Type: number
                  Format: float
                  Description: The draw organizations limit monthly for the user
                  Example: 100





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc",
    "firstname": "Estelle",
    "lastname": "McLaughlin",
    "email": "schuppe.elmore@gmail.com",
    "phone": "(630) 622-5121",
    "locale": "es-cr",
    "last_seen_at": 1764988634,
    "created_at": 1764988634,
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "source_locales": [
      "en_MH"
    ],
    "target_locales": [
      "ps_AF"
    ],
    "settings": {
      "notifications": {
        "new_phrase": [
          "broadcast"
        ],
        "invitation": [
          "broadcast"
        ],
        "added_to_entity": [
          "broadcast"
        ]
      },
      "monthly_credit_usage_limit": 100,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "allow_draw_organizations": true,
      "draw_organizations_limit_monthly": 100
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/projects/{projectId}/users/{userId}
Summary: Update User Project Access
Operation ID: `a18632920ede631709a50ef25e34e92d`

Description: Change the role of a user in an project. Only available for project admins. Only the project owner or an organization admin can change the role of an admin.

Security Requirements:
- bearerAuth

Parameters:
- `projectId` in path (Required): Id of project
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `userId` in path (Required): Id of user.
  Type: string
  Example: `"1045b50e-bf6e-4f5c-b239-2d1ec9e6171d"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: ProjectUserRequest
  Type: object
  Properties:
    role:
      Type: enum
      Enum: ["project_admin", "project_user", "translator"]
      Example: "translator"

    target_locales:
      Type: array
      Items: 
        Type: string
        Example: "sh_YU"

      Description: List of locales translator can translate to. Only send this if role is of type translator.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserProjectAccessResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserProjectAccess
        # Schema: UserProjectAccess
        Type: object
        Properties:
          id:
            Type: string
            Example: "b40f4aa6-66b4-4751-9665-838e1d9e34a4"

          firstname:
            Type: string
            Example: "Jordane"

          lastname:
            Type: string
            Example: "Walter"

          email:
            Type: string
            Example: "lind.kristofer@hotmail.com"

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"



          last_activity_at:
            Type: integer
            Example: 1764988634

          role:
            Type: App\Data\RoleData
            allOf:
              # Schema: RoleData
              Type: object
              Properties:
                value:
                  Type: string
                  Description: Role value
                  Example: "organization_admin"

                label:
                  Type: string
                  Description: Role label
                  Example: "Organization Admin"



          invited_by:
            Type: App\Data\UserData
            allOf:
              # Schema: UserData
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

                firstname:
                  Type: string
                  Example: "Modesto"

                lastname:
                  Type: string
                  Example: "Green"

                email:
                  Type: string
                  Example: "schaden.laron@gmail.com"

                phone:
                  Type: string
                  Example: "(401) 259-3149"

                locale:
                  Type: string
                  Example: "kk_KZ"

                last_seen_at:
                  Type: integer
                  Example: 1764988634

                avatar:
                  Type: App\Data\Avatar
                  allOf:
                    # Schema: Avatar
                    Type: object
                    Properties:
                      width:
                        Type: integer
                        Example: 481

                      height:
                        Type: integer
                        Example: 396

                      original_url:
                        Type: string
                        Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                      thumb_url:
                        Type: string
                        Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                      medium_url:
                        Type: string
                        Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                      id:
                        Type: string
                        Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                      path:
                        Type: string
                        Description: Path of local file
                        Example: "/public/images"


                  Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found



          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "es-cr"

            Description: List of locales user can translate to



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "b40f4aa6-66b4-4751-9665-838e1d9e34a4",
    "firstname": "Jordane",
    "lastname": "Walter",
    "email": "lind.kristofer@hotmail.com",
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "last_activity_at": 1764988634,
    "role": {
      "value": "organization_admin",
      "label": "Organization Admin"
    },
    "invited_by": {
      "id": "dd3fab24-3954-407f-ac04-7e590ca5f632",
      "firstname": "Modesto",
      "lastname": "Green",
      "email": "schaden.laron@gmail.com",
      "phone": "(401) 259-3149",
      "locale": "kk_KZ",
      "last_seen_at": 1764988634,
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      }
    },
    "target_locales": [
      "es-cr"
    ]
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Subscriptions

#### GET /api/subscriptions
Summary: Get all subscriptions
Operation ID: `0317ec171d3ed4b1548bea1e469f944b`

Description: Retrieve all subscriptions with their related plan cycle and user.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: List of subscriptions
  Content-Type: `application/json`
  Schema:
    Type: array
    Items: 
      # Schema: SubscriptionPaginatedResponse
      Type: object
      Properties:
        status:
          Type: boolean
          Description: Response status
          Example: true

        page:
          Type: integer
          Description: Current page number
          Example: 1

        records_per_page:
          Type: integer
          Description: Number of records per page
          Example: 8

        page_count:
          Type: integer
          Description: Number of pages
          Example: 5

        total_records:
          Type: integer
          Description: Total number of items
          Example: 40

        data:
          Type: array
          Items: 
            allOf:
              # Schema: Subscription
              Type: object
              Properties:
                id:
                  Type: string
                  Description: Subscription ID
                  Example: "337c91be-72e4-461d-8810-4934ea433a66"

                user_id:
                  Type: string
                  Description: User ID associated with the subscription
                  Example: "82665bf0-ec00-403c-adb0-fd2ac6ad584b"

                plan_cycle_id:
                  Type: string
                  Description: Plan cycle ID associated with the subscription
                  Example: "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee"

                plan_type:
                  Type: enum
                  Enum: ["free", "business", "enterprise"]
                  Description: Type of the plan
                  Example: "enterprise"

                plan_cycle:
                  Type: enum
                  Enum: ["monthly", "yearly", "lifetime"]
                  Description: Cycle of the plan
                  Example: "lifetime"

                status:
                  Type: string
                  Description: Status of the subscription
                  Example: "active"

                created_at:
                  Type: string
                  Description: Created at
                  Example: "2024-07-01"

                api_usage_units_used:
                  Type: integer
                  Description: API usage units used
                  Example: 100

                invoice_url:
                  Type: string
                  Description: Invoice URL
                  Example: "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf"

                next_billing_date:
                  Type: string
                  Description: Next billing date
                  Example: "2024-07-01"

                expiration_date:
                  Type: string
                  Description: Expiration date
                  Example: "2024-07-01"



          Description: List of items



  Example Response:
```json
[
  {
    "status": true,
    "page": 1,
    "records_per_page": 8,
    "page_count": 5,
    "total_records": 40,
    "data": [
      {
        "id": "337c91be-72e4-461d-8810-4934ea433a66",
        "user_id": "82665bf0-ec00-403c-adb0-fd2ac6ad584b",
        "plan_cycle_id": "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee",
        "plan_type": "enterprise",
        "plan_cycle": "lifetime",
        "status": "active",
        "created_at": "2024-07-01",
        "api_usage_units_used": 100,
        "invoice_url": "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf",
        "next_billing_date": "2024-07-01",
        "expiration_date": "2024-07-01"
      }
    ]
  }
]
```
- 401: Unauthorized

---

#### GET /api/subscriptions/{subscriptionId}
Summary: Get subscription details
Operation ID: `504758ff3ab020a4b9edb0df67063a9b`

Description: Get subscription details by ID

Security Requirements:
- bearerAuth

Parameters:
- `subscriptionId` in path (Required): The ID of the subscription to retrieve

Responses:
- 200: Subscription details
  Content-Type: `application/json`
  Schema:
    # Schema: SubscriptionResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Subscription
        # Schema: Subscription
        Type: object
        Properties:
          id:
            Type: string
            Description: Subscription ID
            Example: "337c91be-72e4-461d-8810-4934ea433a66"

          user_id:
            Type: string
            Description: User ID associated with the subscription
            Example: "82665bf0-ec00-403c-adb0-fd2ac6ad584b"

          plan_cycle_id:
            Type: string
            Description: Plan cycle ID associated with the subscription
            Example: "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee"

          plan_type:
            Type: enum
            Enum: ["free", "business", "enterprise"]
            Description: Type of the plan
            Example: "enterprise"

          plan_cycle:
            Type: enum
            Enum: ["monthly", "yearly", "lifetime"]
            Description: Cycle of the plan
            Example: "lifetime"

          status:
            Type: string
            Description: Status of the subscription
            Example: "active"

          created_at:
            Type: string
            Description: Created at
            Example: "2024-07-01"

          api_usage_units_used:
            Type: integer
            Description: API usage units used
            Example: 100

          invoice_url:
            Type: string
            Description: Invoice URL
            Example: "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf"

          next_billing_date:
            Type: string
            Description: Next billing date
            Example: "2024-07-01"

          expiration_date:
            Type: string
            Description: Expiration date
            Example: "2024-07-01"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "337c91be-72e4-461d-8810-4934ea433a66",
    "user_id": "82665bf0-ec00-403c-adb0-fd2ac6ad584b",
    "plan_cycle_id": "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee",
    "plan_type": "enterprise",
    "plan_cycle": "lifetime",
    "status": "active",
    "created_at": "2024-07-01",
    "api_usage_units_used": 100,
    "invoice_url": "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf",
    "next_billing_date": "2024-07-01",
    "expiration_date": "2024-07-01"
  }
}
```
- 401: Unauthorized
- 404: Subscription not found

---

#### POST /api/subscriptions/{subscriptionId}/reactivate
Summary: Reactivate a subscription
Operation ID: `e615ac97d7fd804b1d073b8621c5a1ea`

Security Requirements:
- bearerAuth

Parameters:
- `subscriptionId` in path (Required): The ID of the subscription to reactivate
  Type: string

Responses:
- 200: Subscription reactivated successfully
  Content-Type: `application/json`
  Schema:
    # Schema: UserSubscriptionResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserSubscription
        # Schema: UserSubscription
        Type: object
        Properties:
          id:
            Type: string
            Description: Subscription ID
            Example: "591f061f-7044-4f19-bb55-5e71d0ee338b"

          user_id:
            Type: string
            Description: User ID
            Example: "4ac3051e-a84a-4cc9-b721-207916d08e32"

          plan_cycle_id:
            Type: string
            Description: Plan cycle ID
            Example: "5ce38c12-d6b0-4e29-9201-4526e96a96c7"

          status:
            Type: string
            Description: Subscription status
            Example: ""

          error:
            Type: string
            Description: Error message
            Example: ""



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "591f061f-7044-4f19-bb55-5e71d0ee338b",
    "user_id": "4ac3051e-a84a-4cc9-b721-207916d08e32",
    "plan_cycle_id": "5ce38c12-d6b0-4e29-9201-4526e96a96c7",
    "status": "",
    "error": ""
  }
}
```
- 404: Subscription not found
  Content-Type: `application/json`
  Schema:
    # Schema: NOT_FOUND_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Entity not found"
        Description: Error description

      code:
        Type: integer
        Default: 404
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/subscriptions/{subscriptionId}/recover
Summary: Recover a frozen subscription
Operation ID: `dd7d0ebe029105dffef13e6764e74eed`

Description: Recover a frozen subscription by either using an existing payment method or adding a new one.

Security Requirements:
- bearerAuth

Request Body:
Required: Yes
Content-Type: `application/json`
Schema:
  # Schema: RecoverSubscriptionRequest
  Type: object
  Properties:
    payment_method_id:
      Type: string
      Description: The ID of the payment method to use for recovery
      Example: "d410a78f-5558-4ba1-9af2-3008052e8a92"

    card_info:
      Type: App\Http\Requests\CreditCardRequest
      allOf:
        # Schema: CreditCardRequest
        Type: object
        Properties:
          cc_number (Required):
            Type: string
            Description: Full credit card number.
            Example: "4111111111111111"

          cc_month (Required):
            Type: string
            Description: Card expiration month (2 digits).
            Example: "02"

          cc_year (Required):
            Type: string
            Description: Card expiration year (4 digits).
            Example: "2025"

          cc_name:
            Type: string
            Description: Cardholder name as it appears on the card.
            Example: "John Doe"

          cc_cvv:
            Type: string
            Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
            Example: "123"

          country_code:
            Type: string
            Description: Two-letter ISO country code where the card was issued or the billing address is located.
            Example: "US"

          billing_address:
            Type: App\Data\BillingAddressData
            allOf:
              # Schema: BillingAddressData
              Type: object
              Properties:
                address_1:
                  Type: string
                  Description: Primary billing address line
                  Example: "Guachipelín de Escazú"

                address_2:
                  Type: string
                  Description: Secondary billing address line
                  Example: "Ofibodegas #5"

                city:
                  Type: string
                  Description: City
                  Example: "Escazú"

                state:
                  Type: string
                  Description: State/Province
                  Example: "San José"

                zip:
                  Type: string
                  Description: ZIP/Postal code
                  Example: "10203"


            Description: Billing address information

          is_default:
            Type: boolean
            Default: false
            Description: Set this card as the default payment method.
            Example: true


      Description: Credit card information for recovery (optional)

    payment_provider:
      Type: enum
      Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
      Description: The payment provider to use for recovery
      Example: "authorize_net"



Responses:
- 200: Subscription recovered successfully
  Content-Type: `application/json`
  Schema:
    # Schema: UserSubscriptionResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserSubscription
        # Schema: UserSubscription
        Type: object
        Properties:
          id:
            Type: string
            Description: Subscription ID
            Example: "591f061f-7044-4f19-bb55-5e71d0ee338b"

          user_id:
            Type: string
            Description: User ID
            Example: "4ac3051e-a84a-4cc9-b721-207916d08e32"

          plan_cycle_id:
            Type: string
            Description: Plan cycle ID
            Example: "5ce38c12-d6b0-4e29-9201-4526e96a96c7"

          status:
            Type: string
            Description: Subscription status
            Example: ""

          error:
            Type: string
            Description: Error message
            Example: ""



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "591f061f-7044-4f19-bb55-5e71d0ee338b",
    "user_id": "4ac3051e-a84a-4cc9-b721-207916d08e32",
    "plan_cycle_id": "5ce38c12-d6b0-4e29-9201-4526e96a96c7",
    "status": "",
    "error": ""
  }
}
```
- 400: Bad Request
- 422: Validation Error

---

#### POST /api/subscriptions/update
Summary: Update user subscription plan (upgrade, downgrade)
Operation ID: `b01d552e1def541c6a9c97cf1c780261`

Description: Update user to a new plan cycle (upgrade, downgrade) and process the change as needed.

Request Body:
Required: Yes
Content-Type: `application/json`
Schema:
  # Schema: UpdateSubscriptionRequest
  Type: object
  Properties:
    user_id (Required):
      Type: string
      Example: "72f1301a-e742-48d3-8672-c50e8bd80bd0"

    plan_type (Required):
      Type: enum
      Enum: ["free", "business", "enterprise"]
      Default: "free"
      Example: "enterprise"

    plan_cycle (Required):
      Type: enum
      Enum: ["monthly", "yearly", "lifetime"]
      Default: "monthly"
      Example: "monthly"

    credit_card:
      Type: App\Data\CreditCardData
      allOf:
        # Schema: CreditCardData
        Type: object
        Properties:
          cc_number:
            Type: string
            Description: Full credit card number.
            Example: "4111111111111111"

          cc_month:
            Type: string
            Description: Card expiration month (2 digits).
            Example: "02"

          cc_year:
            Type: string
            Description: Card expiration year (4 digits).
            Example: "2025"

          cc_name:
            Type: string
            Description: Cardholder name as it appears on the card.
            Example: "John Doe"

          cc_cvv:
            Type: string
            Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
            Example: "123"

          country_code:
            Type: string
            Description: Two-letter ISO country code where the card was issued or the billing address is located.
            Example: "US"

          address_1:
            Type: string
            Description: Address for the card.
            Example: "123 Main St"

          address_2:
            Type: string
            Description: Additional address line for the card.
            Example: "Apt 4B"

          city:
            Type: string
            Description: City for the card.
            Example: "San Francisco"

          state:
            Type: string
            Description: State/province for the card.
            Example: "CA"

          zip:
            Type: string
            Description: ZIP/postal code for the card.
            Example: "94105"





Responses:
- 200: Subscription changed successfully
  Content-Type: `application/json`
  Schema:
    # Schema: SubscriptionResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Subscription
        # Schema: Subscription
        Type: object
        Properties:
          id:
            Type: string
            Description: Subscription ID
            Example: "337c91be-72e4-461d-8810-4934ea433a66"

          user_id:
            Type: string
            Description: User ID associated with the subscription
            Example: "82665bf0-ec00-403c-adb0-fd2ac6ad584b"

          plan_cycle_id:
            Type: string
            Description: Plan cycle ID associated with the subscription
            Example: "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee"

          plan_type:
            Type: enum
            Enum: ["free", "business", "enterprise"]
            Description: Type of the plan
            Example: "enterprise"

          plan_cycle:
            Type: enum
            Enum: ["monthly", "yearly", "lifetime"]
            Description: Cycle of the plan
            Example: "lifetime"

          status:
            Type: string
            Description: Status of the subscription
            Example: "active"

          created_at:
            Type: string
            Description: Created at
            Example: "2024-07-01"

          api_usage_units_used:
            Type: integer
            Description: API usage units used
            Example: 100

          invoice_url:
            Type: string
            Description: Invoice URL
            Example: "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf"

          next_billing_date:
            Type: string
            Description: Next billing date
            Example: "2024-07-01"

          expiration_date:
            Type: string
            Description: Expiration date
            Example: "2024-07-01"



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "337c91be-72e4-461d-8810-4934ea433a66",
    "user_id": "82665bf0-ec00-403c-adb0-fd2ac6ad584b",
    "plan_cycle_id": "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee",
    "plan_type": "enterprise",
    "plan_cycle": "lifetime",
    "status": "active",
    "created_at": "2024-07-01",
    "api_usage_units_used": 100,
    "invoice_url": "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf",
    "next_billing_date": "2024-07-01",
    "expiration_date": "2024-07-01"
  }
}
```
- 422: Validation Error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Translatable Items

#### GET /api/translatable-items/{translatableItemId}
Summary: Get translatable item
Operation ID: `cf9a7d80b1c523f952c2d639b159c508`

Description: Get a single phrase or a content block.

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `translatableItemId` in path (Required): Translatable item ID to delete
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `locale` in query: Locale to get the translations for. If not locale if provided, project base locale will be used.
  Type: string
  Example: `"es-cr"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: TranslatableItemTranslationsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: TranslatableItemTranslations
        # Schema: TranslatableItemTranslations
        Type: object
        Properties:
          id:
            Type: string
            Example: "9c99afd7-38ec-42e0-97fd-da626eeff08a"

          project_id:
            Type: string
            Example: "d951ca8f-8e6f-4d62-b47a-3de9000392dd"

          label:
            Type: string
            Description: Sanitized phrase truncated to 25 chars.
            Example: "About"

          locale:
            Type: string
            Example: "es-es"

          category:
            Type: string
            Description: Phrase or content block context category.
            Example: "UI"

          type:
            Type: enum
            Enum: ["phrase", "content_block"]
            Example: "phrase"

          phrase_id:
            Type: string
            Description: Phrase id. This field will be null if the request is for a content block.
            Example: "e21c852c-99c0-42a7-be81-767716560693"

          phrase:
            Type: string
            Description: Phrase text. This field will be null if the request is for a content block.
            Example: "About"

          translation_id:
            Type: string
            Description: This field will be null if the request is for a content block.
            Example: "8c7d0ab7-b54c-428e-8ce8-42bce0caad08"

          translation:
            Type: string
            Description: Translation text in the locale requested. This field will be null if the request is for a content block.
            Example: "Nosotros"

          translator:
            Type: App\Http\Resources\UserSimpleResource
            allOf:
              # Schema: UserSimple
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                firstname:
                  Type: string
                  Example: "Laisha"

                lastname:
                  Type: string
                  Example: "Eichmann"

                avatar:
                  Type: App\Data\Avatar
                  allOf:
                    # Schema: Avatar
                    Type: object
                    Properties:
                      width:
                        Type: integer
                        Example: 481

                      height:
                        Type: integer
                        Example: 396

                      original_url:
                        Type: string
                        Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                      thumb_url:
                        Type: string
                        Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                      medium_url:
                        Type: string
                        Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                      id:
                        Type: string
                        Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                      path:
                        Type: string
                        Description: Path of local file
                        Example: "/public/images"


                  Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


            Description: User that translated the phrase in case it was translated by a human.

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Machine translator used to translate the phrase.
            Example: "xai"

          content_block_id:
            Type: string
            Description: This field will be null if the translatable item is a phrase.
            Example: "6c2dce67-b078-40c2-9111-ee9dae1c686b"

          custom_id:
            Type: string
            Description: Custom id for content block. This field will be null if the translatable item is a phrase.
            Example: "blE14pfd1$"

          content:
            Type: string
            Description: Content block html content. This field will be null if the translatable item is a phrase.
            Example: "<p>About <strong>us</strong></p>"

          translations:
            Type: array
            Items: 
              allOf:
                # Schema: TranslationWithPhrase
                Type: object
                Properties:
                  id:
                    Type: string
                    Example: "9ced23bd-26af-4d80-82fc-533380c2f756"

                  translation_id:
                    Type: string
                    Example: "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7"

                  label:
                    Type: string
                    Description: Sanitized phrase truncated to 25 chars.
                    Example: "Home"

                  locale:
                    Type: string
                    Example: "es-es"

                  category:
                    Type: string
                    Description: Phrase context category.
                    Example: "UI"

                  phrase:
                    Type: string
                    Example: "Home"

                  phrase_id:
                    Type: string
                    Example: "170e9036-5bc6-4183-aa24-1813c8738d6e"

                  content_block_id:
                    Type: string
                    Example: "49666b64-7eb4-473e-9ab6-2a63b1febe43"

                  translation:
                    Type: string
                    Description: Translation text in the locale provided in this response.
                    Example: "Inicio"

                  untranslated:
                    Type: boolean
                    Example: true

                  translatable:
                    Type: boolean
                    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                    Example: true

                  restorable:
                    Type: boolean
                    Description: Whether this phrase is able to be restored after being marked as untranslatable.
                    Example: false

                  human_translated:
                    Type: boolean
                    Description: Whether translation was done by a human.
                    Example: true

                  memory_translated:
                    Type: boolean
                    Description: Whether translation comes from translation memory.
                    Example: true

                  ai_translated:
                    Type: boolean
                    Description: Whether translation is translated by AI.
                    Example: false

                  translator:
                    Type: App\Http\Resources\UserSimpleResource
                    allOf:
                      # Schema: UserSimple
                      Type: object
                      Properties:
                        id:
                          Type: string
                          Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                        firstname:
                          Type: string
                          Example: "Laisha"

                        lastname:
                          Type: string
                          Example: "Eichmann"

                        avatar:
                          Type: App\Data\Avatar
                          allOf:
                            # Schema: Avatar
                            Type: object
                            Properties:
                              width:
                                Type: integer
                                Example: 481

                              height:
                                Type: integer
                                Example: 396

                              original_url:
                                Type: string
                                Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                              thumb_url:
                                Type: string
                                Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                              medium_url:
                                Type: string
                                Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                              id:
                                Type: string
                                Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                              path:
                                Type: string
                                Description: Path of local file
                                Example: "/public/images"


                          Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                    Description: User that translated the phrase.

                  machine_translator:
                    Type: enum
                    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                    Description: Machine translator used to translate the phrase.
                    Example: "google"

                  words:
                    Type: integer
                    Example: 1

                  created_at:
                    Type: integer
                    Example: 1764988634

                  updated_at:
                    Type: integer
                    Example: 1764988634

                  deleted_at:
                    Type: integer
                    Example: 1764988634



            Description: List of translations for content block. This field will be null if the request is for a single phrase.

          words:
            Type: integer
            Default: 0
            Example: 1

          untranslated:
            Type: boolean
            Default: false
            Example: true

          translatable:
            Type: boolean
            Default: false
            Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
            Example: true

          restorable:
            Type: boolean
            Default: false
            Description: Whether this phrase is able to be restored after being marked as untranslatable.
            Example: false

          human_translated:
            Type: boolean
            Default: false
            Description: Whether translation was done by a human.
            Example: true

          memory_translated:
            Type: boolean
            Default: false
            Description: Whether translation comes from translation memory.
            Example: true

          ai_translated:
            Type: boolean
            Default: false
            Description: Whether translation is translated by AI.
            Example: false

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "9c99afd7-38ec-42e0-97fd-da626eeff08a",
    "project_id": "d951ca8f-8e6f-4d62-b47a-3de9000392dd",
    "label": "About",
    "locale": "es-es",
    "category": "UI",
    "type": "phrase",
    "phrase_id": "e21c852c-99c0-42a7-be81-767716560693",
    "phrase": "About",
    "translation_id": "8c7d0ab7-b54c-428e-8ce8-42bce0caad08",
    "translation": "Nosotros",
    "translator": {
      "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
      "firstname": "Laisha",
      "lastname": "Eichmann",
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      }
    },
    "machine_translator": "xai",
    "content_block_id": "6c2dce67-b078-40c2-9111-ee9dae1c686b",
    "custom_id": "blE14pfd1$",
    "content": "<p>About <strong>us</strong></p>",
    "translations": [
      {
        "id": "9ced23bd-26af-4d80-82fc-533380c2f756",
        "translation_id": "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7",
        "label": "Home",
        "locale": "es-es",
        "category": "UI",
        "phrase": "Home",
        "phrase_id": "170e9036-5bc6-4183-aa24-1813c8738d6e",
        "content_block_id": "49666b64-7eb4-473e-9ab6-2a63b1febe43",
        "translation": "Inicio",
        "untranslated": true,
        "translatable": true,
        "restorable": false,
        "human_translated": true,
        "memory_translated": true,
        "ai_translated": false,
        "translator": {
          "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
          "firstname": "Laisha",
          "lastname": "Eichmann",
          "avatar": {
            "width": 481,
            "height": 396,
            "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
            "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
            "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
            "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
            "path": "/public/images"
          }
        },
        "machine_translator": "google",
        "words": 1,
        "created_at": 1764988634,
        "updated_at": 1764988634,
        "deleted_at": 1764988634
      }
    ],
    "words": 1,
    "untranslated": true,
    "translatable": true,
    "restorable": false,
    "human_translated": true,
    "memory_translated": true,
    "ai_translated": false,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translatable-items
Summary: Create or update translatable items
Operation ID: `7621d67668a64f83cda2cb0902bb4f95`

Description: Create a single or a list of translatable items. If a phrase that already exists is provided, the translatable attribute will be updated if it is different from the existing value. If the custom_id provided for content block already exists, the content block will be updated; this also means phrases, their translatable attributes, and their order will be updated.

Security Requirements:
- bearerAuth
- apiKey

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslatableItemRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Example: "04dd610b-5ceb-40d6-9fcc-e693f14e4edd"

    phrases (Required):
      Type: array
      Items: 
        allOf:
          # Schema: PhraseRequest
          Type: object
          Properties:
            phrase (Required):
              Type: string
              Example: "Home"

            category:
              Type: string
              Description: The category of the phrase. This field is ignored for content block creation, the category send in the root of the request will be used instead.
              Example: "UI"

            translatable:
              Type: boolean
              Description: Whether to mark the phrase as translatable or non-translatable for all locales. If not provided and the phrase already exists, the existing value will be used. If not provided and the phrase does not exist, the phrase will be marked as translatable by default.
              Example: true




    type:
      Type: enum
      Enum: ["phrase", "content_block"]
      Default: "phrase"
      Example: "content_block"

    custom_id:
      Type: string
      Description: Custom id generated by the client to represent and manipulate the content block. Only required if type is content_block.
      Example: "764f14dc-9db1-42e2-83c8-9f9d7310557b"

    content:
      Type: string
      Description: The html content of the content block. Only required if type is content_block.
      Example: "<ul><li>Home</li><li>About</li></ul>"

    category:
      Type: string
      Description: The category of the content block. Only required if type is content_block.
      Example: "UI"

    label:
      Type: string
      Description: Label to identify the content block. If left empty then the first phrase with at least 5 chars will be chosen as the label. Only required if type is content_block.
      Example: "Main Menu"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translatable-items/transfer
Summary: Transfer Translatable Items
Operation ID: `2d8ea726aa3a09a68fbeb3808207ee92`

Description: Transfer all translatable items (phrases and content blocks) from one project to another. This includes standalone phrases and content blocks with their associated phrases.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslatableItemTransferRequest
  Type: object
  Properties:
    source_project_id (Required):
      Type: string
      Example: "0457bbcd-70f1-4abe-ba09-6a101f50598e"

    target_project_id (Required):
      Type: string
      Example: "09559ed1-8b80-4b05-871a-19986f36956c"

    include_translations:
      Type: boolean
      Default: false
      Description: If true, all translations for the project will be transferred, and all memory translations that are realted to the project phrases will be transferred as well.
      Example: true

    create_target_locales:
      Type: boolean
      Default: false
      Description: Whether to create target locales in target project if they do not exist. If false, only translations to existing locales will be transferred.
      Example: true

    force_transfer:
      Type: boolean
      Default: false
      Description: Force transfer even if projects base locale does not match.
      Example: false

    transfer_mode:
      Type: enum
      Enum: ["copy", "move"]
      Default: "move"
      Description: Transfer mode: copy (default) keeps items in source project, move removes them from source project.
      Example: "move"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/translatable-items/{translatableItemId?}
Summary: Delete translatable items
Operation ID: `a7727f4dc248f488269c07a7ad2858a4`

Description: Delete one or multiple translatable items. If multiple items are provided, they must belong to the same project. You may use the translatable item ID as a path parameter to delete a single item or provide an array of IDs in the request body. If both options are provided, the path parameter will be included as part. Optional flags allow deletion of associated translations and memory translations.

Security Requirements:
- bearerAuth

Parameters:
- `translatableItemId?` in path: Translatable item ID to delete
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslatableItemDeleteRequest
  Type: object
  Properties:
    translatable_item_ids:
      Type: array
      Items: 
        Type: string
        Example: "56cb019d-a2b6-47b3-ba07-2bc9e9685a64"

      Description: Array of translatable item IDs to delete

    delete_translations:
      Type: boolean
      Default: false
      Description: Whether to also delete translations
      Example: true

    delete_memory_translations:
      Type: boolean
      Default: false
      Description: Whether to also delete memory translations
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: ArrayOfIdsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success

      data:
        Type: array
        Items: 
          Type: string
          Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

        Description: Deleted IDs


  Example Response:
```json
{
  "status": true,
  "data": [
    "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/translatable-items/all
Summary: Delete all translatable items in a project
Operation ID: `ba8206e91b2ae0a4767193eb60cebcf0`

Description: Delete all translatable items (phrases and content blocks) in a project. All translations and content blocks associated with the project will moved to the trash. Optional flags allow deletion of associated translations and memory translations.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: DeleteAllTranslatableItemsRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Description: Project ID to delete all translatable items from
      Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

    delete_translations:
      Type: boolean
      Default: false
      Description: Whether to also delete translations
      Example: true

    delete_memory_translations:
      Type: boolean
      Default: false
      Description: Whether to also delete memory translations
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Translatable Items - Trash

#### GET /api/translatable-items/trash
Summary: Get Deleted Translatable Items
Operation ID: `495ea016e623b97bc76ebd6c2fc408cf`

Description: Get deleted translatable items from a project.

Security Requirements:
- bearerAuth

Parameters:
- `project_id` in query (Required): Project ID to get deleted items from
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: TrashResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Trash
        # Schema: Trash
        Type: object
        Properties:
          id:
            Type: integer
            Description: The ID of the trashed item
            Example: 1

          entity_type:
            Type: string
            Description: The type of entity that was trashed
            Example: "Project"

          name:
            Type: string
            Description: The name or identifier of the trashed item
            Example: "My Project"

          context:
            Type: App\Data\TrashItemContext
            allOf:
              # Schema: TrashItemContext
              Type: object
              Properties:
                organization:
                  Type: App\Data\OrganizationContext
                  allOf:
                    # Schema: OrganizationContext
                    Type: object
                    Properties:
                      id:
                        Type: string
                        Description: The ID of the organization
                        Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

                      name:
                        Type: string
                        Description: The name of the organization
                        Example: "My Organization"


                  Description: Organization information

                project:
                  Type: App\Data\ProjectContext
                  allOf:
                    # Schema: ProjectContext
                    Type: object
                    Properties:
                      id:
                        Type: string
                        Description: The ID of the project
                        Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

                      name:
                        Type: string
                        Description: The name of the project
                        Example: "My Project"


                  Description: Project information


            Description: Additional context about the trashed item

          deleted_at:
            Type: integer
            Description: When the item was moved to trash
            Example: 1710925800



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "entity_type": "Project",
    "name": "My Project",
    "context": {
      "organization": {
        "id": "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9",
        "name": "My Organization"
      },
      "project": {
        "id": "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9",
        "name": "My Project"
      }
    },
    "deleted_at": 1710925800
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translatable-items/restore
Summary: Restore Deleted Translatable Items
Operation ID: `97500d0c4a20df8e80593d6f16de9f61`

Description: Restore deleted translatable items from a project.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslatableItemRestoreRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Example: "5539b4f0-41a1-4018-ac99-918f3439beca"

    restoreable_ids (Required):
      Type: array
      Items: 
        Type: string
        Example: "92a7a6ce-545a-42a4-94f8-b7194bf28d3d"


    restore_translations:
      Type: boolean
      Default: false
      Example: true

    restore_memory_translations:
      Type: boolean
      Default: false
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translatable-items/restore/all
Summary: Restore All Deleted Translatable Items
Operation ID: `1af22c4e187bc2acc1b556770dd71fc9`

Description: Restore all deleted translatable items from a project.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslatableItemRestoreAllRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Example: "80d47c8c-0be5-4e29-9fa8-a87b02d6cb19"

    restore_translations:
      Type: boolean
      Default: false
      Example: true

    restore_memory_translations:
      Type: boolean
      Default: false
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Translations

#### GET /api/translations
Summary: List translations in flat format
Operation ID: `25ebf0d7654646b66278eadcd144f138`

Description: Get a list of translations. Response will be an object representing a list of translations in the form [Category] => [phrase => translation]. Content blocks will have an additional grouping by their custom_id. Translations follow the following order: [untranslated DESC, ai_translated DESC, memory_translated DESC,  translatable DESC, phrase/label ASC]

Security Requirements:
- bearerAuth
- apiKey

Parameters:
- `project_id` in query (Required): Project ID to get the translations for.
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `locale` in query (Required): No description
  Type: string
  Example: `"es-cr"`
- `format` in query: Response format type. When set to 'data', the response will return the schema shown in the endpoint that has this same structure plus /data at the end. When not provided or when using flat, it returns the schema described in this endpoint.
  Type: enum

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: TranslationManualResponse
    Type: object
    allOf:
      # Schema: OK
      Type: object
      Properties:
        status:
          Type: boolean
          Default: true
          Description: Success


      # Schema: TranslationMeta
      Type: object
      Properties:
        words:
          Type: integer
          Description: Number of translatable words in project..
          Example: "752"

        untranslated:
          Type: integer
          Description: Number of words in project that are yet translated.
          Example: "25"


      Type: object
      Properties:
        data:
          Type: object
          Properties:
            UI:
              Type: object
              Properties:
                Home:
                  Type: string
                  Description: Translation indexed by token inside token category.
                  Example: "Inicio"

                aDcac8503LPQR:
                  Type: object
                  Properties:
                    Content block phrase 1:
                      Type: string
                      Description: Translation indexed by token inside token category.
                      Example: "Frase de bloque de contenido 1"

                    Content block phrase 2:
                      Type: string
                      Description: Translation indexed by token inside token category.
                      Example: "Frase de bloque de contenido 2"

                  Description: Content block custom_id

              Description: Token category

            __uncategorized__:
              Type: object
              Properties:
                Home:
                  Type: string
                  Description: Translation indexed by token inside token category.
                  Example: "Hogar"

              Description: Default category for uncategorized tokens




  Example Response:
```json
{
  "status": true,
  "words": "752",
  "untranslated": "25",
  "data": {
    "UI": {
      "Home": "Inicio",
      "aDcac8503LPQR": {
        "Content block phrase 1": "Frase de bloque de contenido 1",
        "Content block phrase 2": "Frase de bloque de contenido 2"
      }
    },
    "__uncategorized__": {
      "Home": "Hogar"
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/translations/data
Summary: List translations in data format
Operation ID: `2756da8f241f034a0e9f0703871c01ef`

Description: Get a list of translations. Translations will be grouped by category. Translations follow the following order: [untranslated DESC, ai_translated DESC, memory_translated DESC,  translatable DESC, phrase/label ASC]

Security Requirements:
- bearerAuth

Parameters:
- `project_id` in query (Required): Project ID to get the translations for.
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`
- `locale` in query (Required): No description
  Type: string
  Example: `"es-cr"`
- `format` in query: Response format type. When set to 'data', the response will return the schema shown in the endpoint that has this same structure plus /data at the end. When not provided or when using flat, it returns the schema described in this endpoint.
  Type: enum

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: TranslationDataManualResponse
    Type: object
    allOf:
      # Schema: OK
      Type: object
      Properties:
        status:
          Type: boolean
          Default: true
          Description: Success


      # Schema: TranslationMeta
      Type: object
      Properties:
        words:
          Type: integer
          Description: Number of translatable words in project..
          Example: "752"

        untranslated:
          Type: integer
          Description: Number of words in project that are yet translated.
          Example: "25"


      Type: object
      Properties:
        data:
          Type: object
          Properties:
            UI:
              Type: array
              Items: 
                allOf:
                  # Schema: TranslatableItemTranslations
                  Type: object
                  Properties:
                    id:
                      Type: string
                      Example: "9c99afd7-38ec-42e0-97fd-da626eeff08a"

                    project_id:
                      Type: string
                      Example: "d951ca8f-8e6f-4d62-b47a-3de9000392dd"

                    label:
                      Type: string
                      Description: Sanitized phrase truncated to 25 chars.
                      Example: "About"

                    locale:
                      Type: string
                      Example: "es-es"

                    category:
                      Type: string
                      Description: Phrase or content block context category.
                      Example: "UI"

                    type:
                      Type: enum
                      Enum: ["phrase", "content_block"]
                      Example: "phrase"

                    phrase_id:
                      Type: string
                      Description: Phrase id. This field will be null if the request is for a content block.
                      Example: "e21c852c-99c0-42a7-be81-767716560693"

                    phrase:
                      Type: string
                      Description: Phrase text. This field will be null if the request is for a content block.
                      Example: "About"

                    translation_id:
                      Type: string
                      Description: This field will be null if the request is for a content block.
                      Example: "8c7d0ab7-b54c-428e-8ce8-42bce0caad08"

                    translation:
                      Type: string
                      Description: Translation text in the locale requested. This field will be null if the request is for a content block.
                      Example: "Nosotros"

                    translator:
                      Type: App\Http\Resources\UserSimpleResource
                      allOf:
                        # Schema: UserSimple
                        Type: object
                        Properties:
                          id:
                            Type: string
                            Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                          firstname:
                            Type: string
                            Example: "Laisha"

                          lastname:
                            Type: string
                            Example: "Eichmann"

                          avatar:
                            Type: App\Data\Avatar
                            allOf:
                              # Schema: Avatar
                              Type: object
                              Properties:
                                width:
                                  Type: integer
                                  Example: 481

                                height:
                                  Type: integer
                                  Example: 396

                                original_url:
                                  Type: string
                                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                                thumb_url:
                                  Type: string
                                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                                medium_url:
                                  Type: string
                                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                                id:
                                  Type: string
                                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                                path:
                                  Type: string
                                  Description: Path of local file
                                  Example: "/public/images"


                            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                      Description: User that translated the phrase in case it was translated by a human.

                    machine_translator:
                      Type: enum
                      Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                      Default: "default"
                      Description: Machine translator used to translate the phrase.
                      Example: "xai"

                    content_block_id:
                      Type: string
                      Description: This field will be null if the translatable item is a phrase.
                      Example: "6c2dce67-b078-40c2-9111-ee9dae1c686b"

                    custom_id:
                      Type: string
                      Description: Custom id for content block. This field will be null if the translatable item is a phrase.
                      Example: "blE14pfd1$"

                    content:
                      Type: string
                      Description: Content block html content. This field will be null if the translatable item is a phrase.
                      Example: "<p>About <strong>us</strong></p>"

                    translations:
                      Type: array
                      Items: 
                        allOf:
                          # Schema: TranslationWithPhrase
                          Type: object
                          Properties:
                            id:
                              Type: string
                              Example: "9ced23bd-26af-4d80-82fc-533380c2f756"

                            translation_id:
                              Type: string
                              Example: "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7"

                            label:
                              Type: string
                              Description: Sanitized phrase truncated to 25 chars.
                              Example: "Home"

                            locale:
                              Type: string
                              Example: "es-es"

                            category:
                              Type: string
                              Description: Phrase context category.
                              Example: "UI"

                            phrase:
                              Type: string
                              Example: "Home"

                            phrase_id:
                              Type: string
                              Example: "170e9036-5bc6-4183-aa24-1813c8738d6e"

                            content_block_id:
                              Type: string
                              Example: "49666b64-7eb4-473e-9ab6-2a63b1febe43"

                            translation:
                              Type: string
                              Description: Translation text in the locale provided in this response.
                              Example: "Inicio"

                            untranslated:
                              Type: boolean
                              Example: true

                            translatable:
                              Type: boolean
                              Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                              Example: true

                            restorable:
                              Type: boolean
                              Description: Whether this phrase is able to be restored after being marked as untranslatable.
                              Example: false

                            human_translated:
                              Type: boolean
                              Description: Whether translation was done by a human.
                              Example: true

                            memory_translated:
                              Type: boolean
                              Description: Whether translation comes from translation memory.
                              Example: true

                            ai_translated:
                              Type: boolean
                              Description: Whether translation is translated by AI.
                              Example: false

                            translator:
                              Type: App\Http\Resources\UserSimpleResource
                              allOf:
                                # Schema: UserSimple
                                Type: object
                                Properties:
                                  id:
                                    Type: string
                                    Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                                  firstname:
                                    Type: string
                                    Example: "Laisha"

                                  lastname:
                                    Type: string
                                    Example: "Eichmann"

                                  avatar:
                                    Type: App\Data\Avatar
                                    allOf:
                                      # Schema: Avatar
                                      Type: object
                                      Properties:
                                        width:
                                          Type: integer
                                          Example: 481

                                        height:
                                          Type: integer
                                          Example: 396

                                        original_url:
                                          Type: string
                                          Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                                        thumb_url:
                                          Type: string
                                          Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                                        medium_url:
                                          Type: string
                                          Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                                        id:
                                          Type: string
                                          Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                                        path:
                                          Type: string
                                          Description: Path of local file
                                          Example: "/public/images"


                                    Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                              Description: User that translated the phrase.

                            machine_translator:
                              Type: enum
                              Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                              Description: Machine translator used to translate the phrase.
                              Example: "google"

                            words:
                              Type: integer
                              Example: 1

                            created_at:
                              Type: integer
                              Example: 1764988634

                            updated_at:
                              Type: integer
                              Example: 1764988634

                            deleted_at:
                              Type: integer
                              Example: 1764988634



                      Description: List of translations for content block. This field will be null if the request is for a single phrase.

                    words:
                      Type: integer
                      Default: 0
                      Example: 1

                    untranslated:
                      Type: boolean
                      Default: false
                      Example: true

                    translatable:
                      Type: boolean
                      Default: false
                      Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                      Example: true

                    restorable:
                      Type: boolean
                      Default: false
                      Description: Whether this phrase is able to be restored after being marked as untranslatable.
                      Example: false

                    human_translated:
                      Type: boolean
                      Default: false
                      Description: Whether translation was done by a human.
                      Example: true

                    memory_translated:
                      Type: boolean
                      Default: false
                      Description: Whether translation comes from translation memory.
                      Example: true

                    ai_translated:
                      Type: boolean
                      Default: false
                      Description: Whether translation is translated by AI.
                      Example: false

                    created_at:
                      Type: integer
                      Example: 1764988634

                    updated_at:
                      Type: integer
                      Example: 1764988634

                    deleted_at:
                      Type: integer
                      Example: 1764988634



              Description: Token category




  Example Response:
```json
{
  "status": true,
  "words": "752",
  "untranslated": "25",
  "data": {
    "UI": [
      {
        "id": "9c99afd7-38ec-42e0-97fd-da626eeff08a",
        "project_id": "d951ca8f-8e6f-4d62-b47a-3de9000392dd",
        "label": "About",
        "locale": "es-es",
        "category": "UI",
        "type": "phrase",
        "phrase_id": "e21c852c-99c0-42a7-be81-767716560693",
        "phrase": "About",
        "translation_id": "8c7d0ab7-b54c-428e-8ce8-42bce0caad08",
        "translation": "Nosotros",
        "translator": {
          "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
          "firstname": "Laisha",
          "lastname": "Eichmann",
          "avatar": {
            "width": 481,
            "height": 396,
            "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
            "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
            "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
            "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
            "path": "/public/images"
          }
        },
        "machine_translator": "xai",
        "content_block_id": "6c2dce67-b078-40c2-9111-ee9dae1c686b",
        "custom_id": "blE14pfd1$",
        "content": "<p>About <strong>us</strong></p>",
        "translations": [
          {
            "id": "9ced23bd-26af-4d80-82fc-533380c2f756",
            "translation_id": "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7",
            "label": "Home",
            "locale": "es-es",
            "category": "UI",
            "phrase": "Home",
            "phrase_id": "170e9036-5bc6-4183-aa24-1813c8738d6e",
            "content_block_id": "49666b64-7eb4-473e-9ab6-2a63b1febe43",
            "translation": "Inicio",
            "untranslated": true,
            "translatable": true,
            "restorable": false,
            "human_translated": true,
            "memory_translated": true,
            "ai_translated": false,
            "translator": {
              "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
              "firstname": "Laisha",
              "lastname": "Eichmann",
              "avatar": {
                "width": 481,
                "height": 396,
                "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
                "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
                "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
                "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
                "path": "/public/images"
              }
            },
            "machine_translator": "google",
            "words": 1,
            "created_at": 1764988634,
            "updated_at": 1764988634,
            "deleted_at": 1764988634
          }
        ],
        "words": 1,
        "untranslated": true,
        "translatable": true,
        "restorable": false,
        "human_translated": true,
        "memory_translated": true,
        "ai_translated": false,
        "created_at": 1764988634,
        "updated_at": 1764988634,
        "deleted_at": 1764988634
      }
    ]
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### DELETE /api/translations/{translatableItemId}
Summary: Delete translations for a translatable item
Operation ID: `a91515ef59d00773d3c94e96c3a7d9f6`

Description: Delete translations for a translatable item.

Security Requirements:
- bearerAuth

Parameters:
- `translatableItemId` in path (Required): Translatable item ID to delete
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslationDeleteRequest
  Type: object
  Properties:
    locale (Required):
      Type: string
      Description: Translation locale.
      Example: "es-es"

    updateTM:
      Type: boolean
      Default: false
      Description: Whether to delete related entries from translation memory. This field is optional and false if not present.
      Example: true

    delete_language_locales:
      Type: boolean
      Default: false
      Description: If true, all translations for locales of the same language as the locale provided will be deleted as well.
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: SingleTranslationOrContentBlockResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: SingleTranslationOrContentBlock
        # Schema: SingleTranslationOrContentBlock
        Type: object
        Properties:
          id:
            Type: string
            Example: "bbc33428-9b44-485c-8055-b86a202acbc4"

          label:
            Type: string
            Description: Sanitized phrase truncated to 25 chars.
            Example: "About"

          locale:
            Type: string
            Example: "es-es"

          category:
            Type: string
            Description: Phrase or content block context category.
            Example: "UI"

          translation_id:
            Type: string
            Description: This field will be null if the request is for a content block.
            Example: "946972d2-a21d-48c3-8473-b69ddd101101"

          content_block_id:
            Type: string
            Description: This field will be null if the request is for a single phrase.
            Example: "956b274d-fcf5-4390-8fdd-a89afa0d73b9"

          phrase:
            Type: string
            Description: Phrase text. This field will be null if the request is for a content block.
            Example: "About"

          phrase_id:
            Type: string
            Description: Phrase id. This field will be null if the request is for a content block.
            Example: "0b5857d0-2fe2-4047-ab17-a2a3479de60b"

          translation:
            Type: string
            Description: Translation text in the locale requested. This field will be null if the request is for a content block.
            Example: "Nosotros"

          words:
            Type: integer
            Default: 0
            Example: 1

          translations:
            Type: array
            Items: 
              allOf:
                # Schema: TranslationWithPhrase
                Type: object
                Properties:
                  id:
                    Type: string
                    Example: "9ced23bd-26af-4d80-82fc-533380c2f756"

                  translation_id:
                    Type: string
                    Example: "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7"

                  label:
                    Type: string
                    Description: Sanitized phrase truncated to 25 chars.
                    Example: "Home"

                  locale:
                    Type: string
                    Example: "es-es"

                  category:
                    Type: string
                    Description: Phrase context category.
                    Example: "UI"

                  phrase:
                    Type: string
                    Example: "Home"

                  phrase_id:
                    Type: string
                    Example: "170e9036-5bc6-4183-aa24-1813c8738d6e"

                  content_block_id:
                    Type: string
                    Example: "49666b64-7eb4-473e-9ab6-2a63b1febe43"

                  translation:
                    Type: string
                    Description: Translation text in the locale provided in this response.
                    Example: "Inicio"

                  untranslated:
                    Type: boolean
                    Example: true

                  translatable:
                    Type: boolean
                    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                    Example: true

                  restorable:
                    Type: boolean
                    Description: Whether this phrase is able to be restored after being marked as untranslatable.
                    Example: false

                  human_translated:
                    Type: boolean
                    Description: Whether translation was done by a human.
                    Example: true

                  memory_translated:
                    Type: boolean
                    Description: Whether translation comes from translation memory.
                    Example: true

                  ai_translated:
                    Type: boolean
                    Description: Whether translation is translated by AI.
                    Example: false

                  translator:
                    Type: App\Http\Resources\UserSimpleResource
                    allOf:
                      # Schema: UserSimple
                      Type: object
                      Properties:
                        id:
                          Type: string
                          Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                        firstname:
                          Type: string
                          Example: "Laisha"

                        lastname:
                          Type: string
                          Example: "Eichmann"

                        avatar:
                          Type: App\Data\Avatar
                          allOf:
                            # Schema: Avatar
                            Type: object
                            Properties:
                              width:
                                Type: integer
                                Example: 481

                              height:
                                Type: integer
                                Example: 396

                              original_url:
                                Type: string
                                Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                              thumb_url:
                                Type: string
                                Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                              medium_url:
                                Type: string
                                Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                              id:
                                Type: string
                                Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                              path:
                                Type: string
                                Description: Path of local file
                                Example: "/public/images"


                          Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                    Description: User that translated the phrase.

                  machine_translator:
                    Type: enum
                    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                    Description: Machine translator used to translate the phrase.
                    Example: "google"

                  words:
                    Type: integer
                    Example: 1

                  created_at:
                    Type: integer
                    Example: 1764988634

                  updated_at:
                    Type: integer
                    Example: 1764988634

                  deleted_at:
                    Type: integer
                    Example: 1764988634



            Description: List of translations for content block. This field will be null if the request is for a single phrase.

          untranslated:
            Type: boolean
            Default: false
            Example: true

          translatable:
            Type: boolean
            Default: false
            Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
            Example: true

          restorable:
            Type: boolean
            Default: false
            Description: Whether this phrase is able to be restored after being marked as untranslatable.
            Example: false

          human_translated:
            Type: boolean
            Default: false
            Description: Whether translation was done by a human.
            Example: true

          memory_translated:
            Type: boolean
            Default: false
            Description: Whether translation comes from translation memory.
            Example: true

          ai_translated:
            Type: boolean
            Default: false
            Description: Whether translation is translated by AI.
            Example: false



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "bbc33428-9b44-485c-8055-b86a202acbc4",
    "label": "About",
    "locale": "es-es",
    "category": "UI",
    "translation_id": "946972d2-a21d-48c3-8473-b69ddd101101",
    "content_block_id": "956b274d-fcf5-4390-8fdd-a89afa0d73b9",
    "phrase": "About",
    "phrase_id": "0b5857d0-2fe2-4047-ab17-a2a3479de60b",
    "translation": "Nosotros",
    "words": 1,
    "translations": [
      {
        "id": "9ced23bd-26af-4d80-82fc-533380c2f756",
        "translation_id": "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7",
        "label": "Home",
        "locale": "es-es",
        "category": "UI",
        "phrase": "Home",
        "phrase_id": "170e9036-5bc6-4183-aa24-1813c8738d6e",
        "content_block_id": "49666b64-7eb4-473e-9ab6-2a63b1febe43",
        "translation": "Inicio",
        "untranslated": true,
        "translatable": true,
        "restorable": false,
        "human_translated": true,
        "memory_translated": true,
        "ai_translated": false,
        "translator": {
          "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
          "firstname": "Laisha",
          "lastname": "Eichmann",
          "avatar": {
            "width": 481,
            "height": 396,
            "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
            "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
            "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
            "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
            "path": "/public/images"
          }
        },
        "machine_translator": "google",
        "words": 1,
        "created_at": 1764988634,
        "updated_at": 1764988634,
        "deleted_at": 1764988634
      }
    ],
    "untranslated": true,
    "translatable": true,
    "restorable": false,
    "human_translated": true,
    "memory_translated": true,
    "ai_translated": false
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/translations/{translatableItemId}
Summary: Create or update translations for a translatable item
Operation ID: `406149ff2b387b172c512b2dabae42a1`

Description: Create a translation for a phrase or a list of translations for a content block. When creating translations for a content block, then a list of phrases and translations must be provided. Translation text is only required if not using the endpoint to mark a phrase as untranslatable for a locale. When marking the phrase as untranslatable, all target locales of the same language of the requested locale will be marked as untranslatable as well. This only applies for target locales the user has access to; translators will have access to target locales assigned to them, while staff users will have access to all target locales.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslationCreateRequest
  Type: object
  Properties:
    locale (Required):
      Type: string
      Description: Translation locale.
      Example: "es-es"

    translation:
      Type: string
      Description: Translation in queried locale. Only required if translatable is true and request is for a single phrase; otherwise this field will be ignored.
      Example: "Inicio"

    updateTM:
      Type: boolean
      Default: false
      Description: Whether to save this translation in translation memory.
      Example: true

    translatable:
      Type: boolean
      Default: true
      Description: If this flag is false, the translatable item will be marked as untranslatable for requested locale and all project target locales of the same language the user has access to.
      Example: true

    translations:
      Type: array
      Items: 
        allOf:
          # Schema: TranslationData
          Type: object
          Properties:
            phrase_id:
              Type: string
              Description: ID of the phrase to translate.
              Example: "123e4567-e89b-12d3-a456-426614174000"

            translation:
              Type: string
              Description: Translation in queried locale.
              Example: "Inicio"

            translatable:
              Type: boolean
              Default: true
              Description: If this flag is false, the phrase will be marked as untranslatable for requested locale and all project target locales of the same language the user has access to. Default: true
              Example: true



      Description: List of translations for content block. Should be used when translatable item is a content block, unless marking all phrases inside the content block as untranslatable. In that case the translatable field sibling to this one must be set to false and this field can be left empty.



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: TranslatableItemTranslationsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: TranslatableItemTranslations
        # Schema: TranslatableItemTranslations
        Type: object
        Properties:
          id:
            Type: string
            Example: "9c99afd7-38ec-42e0-97fd-da626eeff08a"

          project_id:
            Type: string
            Example: "d951ca8f-8e6f-4d62-b47a-3de9000392dd"

          label:
            Type: string
            Description: Sanitized phrase truncated to 25 chars.
            Example: "About"

          locale:
            Type: string
            Example: "es-es"

          category:
            Type: string
            Description: Phrase or content block context category.
            Example: "UI"

          type:
            Type: enum
            Enum: ["phrase", "content_block"]
            Example: "phrase"

          phrase_id:
            Type: string
            Description: Phrase id. This field will be null if the request is for a content block.
            Example: "e21c852c-99c0-42a7-be81-767716560693"

          phrase:
            Type: string
            Description: Phrase text. This field will be null if the request is for a content block.
            Example: "About"

          translation_id:
            Type: string
            Description: This field will be null if the request is for a content block.
            Example: "8c7d0ab7-b54c-428e-8ce8-42bce0caad08"

          translation:
            Type: string
            Description: Translation text in the locale requested. This field will be null if the request is for a content block.
            Example: "Nosotros"

          translator:
            Type: App\Http\Resources\UserSimpleResource
            allOf:
              # Schema: UserSimple
              Type: object
              Properties:
                id:
                  Type: string
                  Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                firstname:
                  Type: string
                  Example: "Laisha"

                lastname:
                  Type: string
                  Example: "Eichmann"

                avatar:
                  Type: App\Data\Avatar
                  allOf:
                    # Schema: Avatar
                    Type: object
                    Properties:
                      width:
                        Type: integer
                        Example: 481

                      height:
                        Type: integer
                        Example: 396

                      original_url:
                        Type: string
                        Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                      thumb_url:
                        Type: string
                        Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                      medium_url:
                        Type: string
                        Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                      id:
                        Type: string
                        Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                      path:
                        Type: string
                        Description: Path of local file
                        Example: "/public/images"


                  Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


            Description: User that translated the phrase in case it was translated by a human.

          machine_translator:
            Type: enum
            Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
            Default: "default"
            Description: Machine translator used to translate the phrase.
            Example: "xai"

          content_block_id:
            Type: string
            Description: This field will be null if the translatable item is a phrase.
            Example: "6c2dce67-b078-40c2-9111-ee9dae1c686b"

          custom_id:
            Type: string
            Description: Custom id for content block. This field will be null if the translatable item is a phrase.
            Example: "blE14pfd1$"

          content:
            Type: string
            Description: Content block html content. This field will be null if the translatable item is a phrase.
            Example: "<p>About <strong>us</strong></p>"

          translations:
            Type: array
            Items: 
              allOf:
                # Schema: TranslationWithPhrase
                Type: object
                Properties:
                  id:
                    Type: string
                    Example: "9ced23bd-26af-4d80-82fc-533380c2f756"

                  translation_id:
                    Type: string
                    Example: "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7"

                  label:
                    Type: string
                    Description: Sanitized phrase truncated to 25 chars.
                    Example: "Home"

                  locale:
                    Type: string
                    Example: "es-es"

                  category:
                    Type: string
                    Description: Phrase context category.
                    Example: "UI"

                  phrase:
                    Type: string
                    Example: "Home"

                  phrase_id:
                    Type: string
                    Example: "170e9036-5bc6-4183-aa24-1813c8738d6e"

                  content_block_id:
                    Type: string
                    Example: "49666b64-7eb4-473e-9ab6-2a63b1febe43"

                  translation:
                    Type: string
                    Description: Translation text in the locale provided in this response.
                    Example: "Inicio"

                  untranslated:
                    Type: boolean
                    Example: true

                  translatable:
                    Type: boolean
                    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
                    Example: true

                  restorable:
                    Type: boolean
                    Description: Whether this phrase is able to be restored after being marked as untranslatable.
                    Example: false

                  human_translated:
                    Type: boolean
                    Description: Whether translation was done by a human.
                    Example: true

                  memory_translated:
                    Type: boolean
                    Description: Whether translation comes from translation memory.
                    Example: true

                  ai_translated:
                    Type: boolean
                    Description: Whether translation is translated by AI.
                    Example: false

                  translator:
                    Type: App\Http\Resources\UserSimpleResource
                    allOf:
                      # Schema: UserSimple
                      Type: object
                      Properties:
                        id:
                          Type: string
                          Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

                        firstname:
                          Type: string
                          Example: "Laisha"

                        lastname:
                          Type: string
                          Example: "Eichmann"

                        avatar:
                          Type: App\Data\Avatar
                          allOf:
                            # Schema: Avatar
                            Type: object
                            Properties:
                              width:
                                Type: integer
                                Example: 481

                              height:
                                Type: integer
                                Example: 396

                              original_url:
                                Type: string
                                Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                              thumb_url:
                                Type: string
                                Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                              medium_url:
                                Type: string
                                Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                              id:
                                Type: string
                                Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                              path:
                                Type: string
                                Description: Path of local file
                                Example: "/public/images"


                          Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.


                    Description: User that translated the phrase.

                  machine_translator:
                    Type: enum
                    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                    Description: Machine translator used to translate the phrase.
                    Example: "google"

                  words:
                    Type: integer
                    Example: 1

                  created_at:
                    Type: integer
                    Example: 1764988634

                  updated_at:
                    Type: integer
                    Example: 1764988634

                  deleted_at:
                    Type: integer
                    Example: 1764988634



            Description: List of translations for content block. This field will be null if the request is for a single phrase.

          words:
            Type: integer
            Default: 0
            Example: 1

          untranslated:
            Type: boolean
            Default: false
            Example: true

          translatable:
            Type: boolean
            Default: false
            Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
            Example: true

          restorable:
            Type: boolean
            Default: false
            Description: Whether this phrase is able to be restored after being marked as untranslatable.
            Example: false

          human_translated:
            Type: boolean
            Default: false
            Description: Whether translation was done by a human.
            Example: true

          memory_translated:
            Type: boolean
            Default: false
            Description: Whether translation comes from translation memory.
            Example: true

          ai_translated:
            Type: boolean
            Default: false
            Description: Whether translation is translated by AI.
            Example: false

          created_at:
            Type: integer
            Example: 1764988634

          updated_at:
            Type: integer
            Example: 1764988634

          deleted_at:
            Type: integer
            Example: 1764988634



  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "9c99afd7-38ec-42e0-97fd-da626eeff08a",
    "project_id": "d951ca8f-8e6f-4d62-b47a-3de9000392dd",
    "label": "About",
    "locale": "es-es",
    "category": "UI",
    "type": "phrase",
    "phrase_id": "e21c852c-99c0-42a7-be81-767716560693",
    "phrase": "About",
    "translation_id": "8c7d0ab7-b54c-428e-8ce8-42bce0caad08",
    "translation": "Nosotros",
    "translator": {
      "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
      "firstname": "Laisha",
      "lastname": "Eichmann",
      "avatar": {
        "width": 481,
        "height": 396,
        "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
        "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
        "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
        "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
        "path": "/public/images"
      }
    },
    "machine_translator": "xai",
    "content_block_id": "6c2dce67-b078-40c2-9111-ee9dae1c686b",
    "custom_id": "blE14pfd1$",
    "content": "<p>About <strong>us</strong></p>",
    "translations": [
      {
        "id": "9ced23bd-26af-4d80-82fc-533380c2f756",
        "translation_id": "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7",
        "label": "Home",
        "locale": "es-es",
        "category": "UI",
        "phrase": "Home",
        "phrase_id": "170e9036-5bc6-4183-aa24-1813c8738d6e",
        "content_block_id": "49666b64-7eb4-473e-9ab6-2a63b1febe43",
        "translation": "Inicio",
        "untranslated": true,
        "translatable": true,
        "restorable": false,
        "human_translated": true,
        "memory_translated": true,
        "ai_translated": false,
        "translator": {
          "id": "a37651e3-3045-4aaa-b47e-3b88fdd29041",
          "firstname": "Laisha",
          "lastname": "Eichmann",
          "avatar": {
            "width": 481,
            "height": 396,
            "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
            "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
            "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
            "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
            "path": "/public/images"
          }
        },
        "machine_translator": "google",
        "words": 1,
        "created_at": 1764988634,
        "updated_at": 1764988634,
        "deleted_at": 1764988634
      }
    ],
    "words": 1,
    "untranslated": true,
    "translatable": true,
    "restorable": false,
    "human_translated": true,
    "memory_translated": true,
    "ai_translated": false,
    "created_at": 1764988634,
    "updated_at": 1764988634,
    "deleted_at": 1764988634
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### Translations - Trash

#### GET /api/translations/trash
Summary: View deleted translations
Operation ID: `01540b81c551faa21542773cb94c2a8c`

Description: Retrieve translations that have been deleted within the last 45 days.

Security Requirements:
- bearerAuth

Parameters:
- `project_id` in query (Required): Project ID to get deleted translations from
  Type: string
  Example: `"10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    Type: array
    Items: 
      # Schema: DeletedTranslationResponse
      Type: object
      Properties:
        status:
          Type: boolean
          Description: Response status
          Example: true

        data:
          # Inferred schema: DeletedTranslation
          # Schema: DeletedTranslation
          Type: object
          Properties:
            id:
              Type: string
              Example: "a1d97633-a1cb-436b-9390-a8155896b5ae"

            locale:
              Type: string
              Description: Translation locale.
              Example: "es-es"

            category:
              Type: string
              Description: Phrase context category.
              Example: "UI"

            phrase:
              Type: string
              Example: "Hello"

            phrase_id:
              Type: string
              Example: "83df7761-4e3e-4d1f-9f60-cf1e47ef962f"

            translation:
              Type: string
              Description: Translation text in the locale provided in this response.
              Example: "Hola"

            translatable:
              Type: boolean
              Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
              Example: true

            memory_translation:
              Type: boolean
              Example: true

            created_at:
              Type: integer
              Example: 1764988634

            updated_at:
              Type: integer
              Example: 1764988634

            deleted_at:
              Type: integer
              Example: 1764988634




  Example Response:
```json
[
  {
    "status": true,
    "data": {
      "id": "a1d97633-a1cb-436b-9390-a8155896b5ae",
      "locale": "es-es",
      "category": "UI",
      "phrase": "Hello",
      "phrase_id": "83df7761-4e3e-4d1f-9f60-cf1e47ef962f",
      "translation": "Hola",
      "translatable": true,
      "memory_translation": true,
      "created_at": 1764988634,
      "updated_at": 1764988634,
      "deleted_at": 1764988634
    }
  }
]
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translations/restore
Summary: Restore a list of translations.
Operation ID: `8d65385843cdd0313f23e58c7228f01d`

Description: Restore translations that have been deleted within the last 45 days. Endpoint expects a list of translation ids, which can be retrieved from the trash endpoint. To restore translations for translatable items, use the translatable-items/restore endpoints.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslationRestoreRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Example: "e936c54e-676e-4446-81e3-11face56e37a"

    restoreable_ids (Required):
      Type: array
      Items: 
        Type: string
        Example: "78630ab3-86cf-48fb-ac4c-09f6ab337843"




Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/translations/restore/all
Summary: Restore all translations
Operation ID: `ddb5fc76a50964c0c481892b8936a2d0`

Description: Restore all deleted translations for a project deleted within the last 45 days.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TranslationRestoreAllRequest
  Type: object
  Properties:
    project_id (Required):
      Type: string
      Example: "7cd76a80-8c54-4de2-96c5-a258632140c1"

    restore_memory_translations:
      Type: boolean
      Default: false
      Example: true



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### User - Balance

#### GET /api/me/credit/balance
Summary: Get User Credit Balance
Operation ID: `ccce35e3e6c080dff361df40a0d5ab73`

Description: Get the credit balance of the authenticated user.

Security Requirements:
- bearerAuth

Parameters:
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransactionPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: BalanceTransaction
            Type: object
            Properties:
              id:
                Type: string
                Description: Transaction ID
                Example: "123e4567-e89b-12d3-a456-426614174001"

              entity_id:
                Type: string
                Description: Entity ID
                Example: "123e4567-e89b-12d3-a456-426614174000"

              entity_type:
                Type: string
                Description: Entity type (user, organization, project)
                Example: "user"

              amount:
                Type: number
                Format: float
                Description: Transaction amount
                Example: 50

              type:
                Type: enum
                Enum: ["credit", "auto_recharge", "machine_translation", "draw_from_account", "draw_from_organization", "prepaid_credits_invoiced", "free_credits_granted", "prepaid_credits_transfer", "free_credits_transfer"]
                Description: Transaction type
                Example: "prepaid_credits_transfer"

              balance_before:
                Type: number
                Format: float
                Description: Balance before transaction
                Example: 50

              balance_after:
                Type: number
                Format: float
                Description: Balance after transaction
                Example: 50

              prepaid_credit:
                Type: boolean
                Description: Pre-paid credit
                Example: true

              reference_entity_id:
                Type: string
                Description: Reference Entity ID
                Example: "ref_123"

              reference_entity_type:
                Type: string
                Description: Reference Entity Type
                Example: "user"

              payment_provider:
                Type: enum
                Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
                Description: Payment provider
                Example: "authorize_net"

              machine_translator:
                Type: enum
                Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
                Description: Machine translator used in Transaction
                Example: "google"

              created_at:
                Type: integer
                Description: Transaction date
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174001",
      "entity_id": "123e4567-e89b-12d3-a456-426614174000",
      "entity_type": "user",
      "amount": 50,
      "type": "prepaid_credits_transfer",
      "balance_before": 50,
      "balance_after": 50,
      "prepaid_credit": true,
      "reference_entity_id": "ref_123",
      "reference_entity_type": "user",
      "payment_provider": "authorize_net",
      "machine_translator": "google",
      "created_at": 1764988634
    }
  ]
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/me/credit
Summary: Get User Credit
Operation ID: `ce200459db95da15dde7c464d13dca2c`

Description: Get the credit of the authenticated user.

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Balance
        # Schema: Balance
        Type: object
        Properties:
          total_balance:
            Type: number
            Format: float
            Description: Total balance.
            Example: 100

          prepaid_credits_balance:
            Type: number
            Format: float
            Description: Prepaid credits balance.
            Example: 50

          free_credits_balance:
            Type: number
            Format: float
            Description: Free credits balance.
            Example: 50



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_balance": 100,
    "prepaid_credits_balance": 50,
    "free_credits_balance": 50
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/me/credit
Summary: Add Credit to User
Operation ID: `d32dc4d41b339f3d0c49f4900cf5a486`

Description: Add credit to a user's balance.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: AddCreditRequest
  Type: object
  Properties:
    amount (Required):
      Type: number
      Format: float
      Description: Amount of credit to add.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: Balance
        # Schema: Balance
        Type: object
        Properties:
          total_balance:
            Type: number
            Format: float
            Description: Total balance.
            Example: 100

          prepaid_credits_balance:
            Type: number
            Format: float
            Description: Prepaid credits balance.
            Example: 50

          free_credits_balance:
            Type: number
            Format: float
            Description: Free credits balance.
            Example: 50



  Example Response:
```json
{
  "status": true,
  "data": {
    "total_balance": 100,
    "prepaid_credits_balance": 50,
    "free_credits_balance": 50
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/me/credit/transfer/project/{project}
Summary: Transfer Credits to Project
Operation ID: `df3bf7609a12e18014fc629e88b86e48`

Security Requirements:
- bearerAuth

Parameters:
- `project` in path (Required): No description
  Type: string

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TransferCreditRequest
  Type: object
  Properties:
    prepaid_credits:
      Type: number
      Format: float
      Description: Amount of prepaid credits to transfer.
      Example: 100

    free_credits:
      Type: number
      Format: float
      Description: Amount of free credits to transfer.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransferResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceTransfer
        # Schema: BalanceTransfer
        Type: object
        Properties:
          source_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the source entity after transfer

          destination_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the destination entity after transfer



  Example Response:
```json
{
  "status": true,
  "data": {
    "source_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    },
    "destination_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    }
  }
}
```
- 400: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 403: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### POST /api/me/credit/transfer/organization/{organization}
Summary: Transfer Credits to Organization
Operation ID: `e90c33aa996404b5a1c46dbddb77d4a5`

Security Requirements:
- bearerAuth

Parameters:
- `organization` in path (Required): No description
  Type: string

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: TransferCreditRequest
  Type: object
  Properties:
    prepaid_credits:
      Type: number
      Format: float
      Description: Amount of prepaid credits to transfer.
      Example: 100

    free_credits:
      Type: number
      Format: float
      Description: Amount of free credits to transfer.
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceTransferResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceTransfer
        # Schema: BalanceTransfer
        Type: object
        Properties:
          source_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the source entity after transfer

          destination_balance:
            Type: App\Http\Resources\BalanceResource
            allOf:
              # Schema: Balance
              Type: object
              Properties:
                total_balance:
                  Type: number
                  Format: float
                  Description: Total balance.
                  Example: 100

                prepaid_credits_balance:
                  Type: number
                  Format: float
                  Description: Prepaid credits balance.
                  Example: 50

                free_credits_balance:
                  Type: number
                  Format: float
                  Description: Free credits balance.
                  Example: 50


            Description: Balance information for the destination entity after transfer



  Example Response:
```json
{
  "status": true,
  "data": {
    "source_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    },
    "destination_balance": {
      "total_balance": 100,
      "prepaid_credits_balance": 50,
      "free_credits_balance": 50
    }
  }
}
```
- 400: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 403: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/me/credit/summary
Summary: Get User Credit Usage Summary
Operation ID: `8a740dd45ec50ce395c2688973a4e3fd`

Description: Get the aggregated credit usage summary of the authenticated user, optionally filtered by date range.

Security Requirements:
- bearerAuth

Parameters:
- `start_date` in query: Start date for the activity range
  Type: string
  Example: `"2024-01-01"`
- `end_date` in query: End date for the activity range
  Type: string
  Example: `"2024-01-31"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: BalanceSummaryResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: BalanceSummary
        # Schema: BalanceSummary
        Type: object
        Properties:
          prepaid_credits_used:
            Type: number
            Format: float
            Description: Total prepaid credits used in the period
            Example: 1000

          prepaid_credits_available:
            Type: number
            Format: float
            Description: Total prepaid credits currently available
            Example: 2000

          free_credits_used:
            Type: number
            Format: float
            Description: Total free credits used in the period
            Example: 500

          free_credits_available:
            Type: number
            Format: float
            Description: Total free credits currently available
            Example: 1500

          total_credits_used:
            Type: number
            Format: float
            Description: Total credits used in the period
            Example: 1500

          total_credits_available:
            Type: number
            Format: float
            Description: Total credits currently available
            Example: 2000



  Example Response:
```json
{
  "status": true,
  "data": {
    "prepaid_credits_used": 1000,
    "prepaid_credits_available": 2000,
    "free_credits_used": 500,
    "free_credits_available": 1500,
    "total_credits_used": 1500,
    "total_credits_available": 2000
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

### User

#### GET /api/me
Summary: Get User Profile
Operation ID: `ea08752f32c0fd05c1a43fb4933e9870`

Description: Get your profile information

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserExtendedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserExtended
        # Schema: UserExtended
        Type: object
        Properties:
          id:
            Type: string
            Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

          firstname:
            Type: string
            Example: "Estelle"

          lastname:
            Type: string
            Example: "McLaughlin"

          email:
            Type: string
            Example: "schuppe.elmore@gmail.com"

          phone:
            Type: string
            Example: "(630) 622-5121"

          locale:
            Type: string
            Example: "es-cr"

          last_seen_at:
            Type: integer
            Description: Unix timestamp indicating last time the user interacted with the system.
            Example: 1764988634

          created_at:
            Type: integer
            Description: Unix timestamp indicating creation date.
            Example: 1764988634

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"


            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

          source_locales:
            Type: array
            Items: 
              Type: string
              Example: "en_MH"

            Description: List of locales user can translate from

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "ps_AF"

            Description: List of locales user can translate to

          settings:
            Type: App\Data\UserSettingsData
            allOf:
              # Schema: UserSettingsData
              Type: object
              Properties:
                notifications:
                  Type: App\Data\UserNotificationSettings
                  allOf:
                    # Schema: UserNotificationSettings
                    Type: object
                    Properties:
                      new_phrase:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      invitation:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      added_to_entity:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


                  Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: The maximum amount that can be drawn from the monthly balance of the user.
                  Example: 100

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Whether auto recharge is enabled for the user
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
                  Example: 20

                allow_draw_organizations:
                  Type: boolean
                  Default: true
                  Description: The allow draw organizations for the user
                  Example: true

                draw_organizations_limit_monthly:
                  Type: number
                  Format: float
                  Description: The draw organizations limit monthly for the user
                  Example: 100





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc",
    "firstname": "Estelle",
    "lastname": "McLaughlin",
    "email": "schuppe.elmore@gmail.com",
    "phone": "(630) 622-5121",
    "locale": "es-cr",
    "last_seen_at": 1764988634,
    "created_at": 1764988634,
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "source_locales": [
      "en_MH"
    ],
    "target_locales": [
      "ps_AF"
    ],
    "settings": {
      "notifications": {
        "new_phrase": [
          "broadcast"
        ],
        "invitation": [
          "broadcast"
        ],
        "added_to_entity": [
          "broadcast"
        ]
      },
      "monthly_credit_usage_limit": 100,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "allow_draw_organizations": true,
      "draw_organizations_limit_monthly": 100
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/me
Summary: Update User Profile
Operation ID: `76c9be3386dd6da39b44640ebb044381`

Description: Update your profile.

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: UserRequest
  Type: object
  Properties:
    firstname:
      Type: string
      Example: "Deion"

    lastname:
      Type: string
      Example: "Crona"

    phone:
      Type: string
      Example: "1-223-964-6120"

    locale:
      Type: string
      Example: "sr_CS"

    avatar:
      Type: App\Data\Avatar
      allOf:
        # Schema: Avatar
        Type: object
        Properties:
          width:
            Type: integer
            Example: 481

          height:
            Type: integer
            Example: 396

          original_url:
            Type: string
            Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

          thumb_url:
            Type: string
            Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

          medium_url:
            Type: string
            Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

          id:
            Type: string
            Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

          path:
            Type: string
            Description: Path of local file
            Example: "/public/images"


      Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

    source_locales:
      Type: array
      Items: 
        Type: string
        Example: "kn_IN"

      Description: List of locales user can translate from

    target_locales:
      Type: array
      Items: 
        Type: string
        Example: "hi_IN"

      Description: List of locales user can translate to

    auto_recharge_credit_card_id:
      Type: string
      Example: "f2422b72-930f-41f7-a60b-7fcc8a42a388"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserExtendedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserExtended
        # Schema: UserExtended
        Type: object
        Properties:
          id:
            Type: string
            Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

          firstname:
            Type: string
            Example: "Estelle"

          lastname:
            Type: string
            Example: "McLaughlin"

          email:
            Type: string
            Example: "schuppe.elmore@gmail.com"

          phone:
            Type: string
            Example: "(630) 622-5121"

          locale:
            Type: string
            Example: "es-cr"

          last_seen_at:
            Type: integer
            Description: Unix timestamp indicating last time the user interacted with the system.
            Example: 1764988634

          created_at:
            Type: integer
            Description: Unix timestamp indicating creation date.
            Example: 1764988634

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"


            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

          source_locales:
            Type: array
            Items: 
              Type: string
              Example: "en_MH"

            Description: List of locales user can translate from

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "ps_AF"

            Description: List of locales user can translate to

          settings:
            Type: App\Data\UserSettingsData
            allOf:
              # Schema: UserSettingsData
              Type: object
              Properties:
                notifications:
                  Type: App\Data\UserNotificationSettings
                  allOf:
                    # Schema: UserNotificationSettings
                    Type: object
                    Properties:
                      new_phrase:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      invitation:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      added_to_entity:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


                  Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: The maximum amount that can be drawn from the monthly balance of the user.
                  Example: 100

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Whether auto recharge is enabled for the user
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
                  Example: 20

                allow_draw_organizations:
                  Type: boolean
                  Default: true
                  Description: The allow draw organizations for the user
                  Example: true

                draw_organizations_limit_monthly:
                  Type: number
                  Format: float
                  Description: The draw organizations limit monthly for the user
                  Example: 100





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc",
    "firstname": "Estelle",
    "lastname": "McLaughlin",
    "email": "schuppe.elmore@gmail.com",
    "phone": "(630) 622-5121",
    "locale": "es-cr",
    "last_seen_at": 1764988634,
    "created_at": 1764988634,
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "source_locales": [
      "en_MH"
    ],
    "target_locales": [
      "ps_AF"
    ],
    "settings": {
      "notifications": {
        "new_phrase": [
          "broadcast"
        ],
        "invitation": [
          "broadcast"
        ],
        "added_to_entity": [
          "broadcast"
        ]
      },
      "monthly_credit_usage_limit": 100,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "allow_draw_organizations": true,
      "draw_organizations_limit_monthly": 100
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/me/settings
Summary: Get User Settings
Operation ID: `fdc3038b8350c4326c1e298ad2300af6`

Description: Get your profile settings

Security Requirements:
- bearerAuth

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserSettingsResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserSettings
        # Schema: UserSettings
        Type: object
        Properties:
          notifications:
            Type: App\Data\UserNotificationSettings
            allOf:
              # Schema: UserNotificationSettings
              Type: object
              Properties:
                new_phrase:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                invitation:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                added_to_entity:
                  Type: array
                  Items: 
                    Type: string
                    Example: "broadcast"

                  Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


            Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

          monthly_credit_usage_limit:
            Type: number
            Format: float
            Description: The maximum amount that can be drawn from the monthly balance of the user.
            Example: 100

          auto_recharge_enabled:
            Type: boolean
            Default: false
            Description: Whether auto recharge is enabled for the user
            Example: true

          auto_recharge_threshold:
            Type: number
            Format: float
            Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
            Example: 20

          auto_recharge_amount:
            Type: number
            Format: float
            Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
            Example: 20

          allow_draw_organizations:
            Type: boolean
            Default: true
            Description: The allow draw organizations for the user
            Example: true

          draw_organizations_limit_monthly:
            Type: number
            Format: float
            Description: The draw organizations limit monthly for the user
            Example: 100



  Example Response:
```json
{
  "status": true,
  "data": {
    "notifications": {
      "new_phrase": [
        "broadcast"
      ],
      "invitation": [
        "broadcast"
      ],
      "added_to_entity": [
        "broadcast"
      ]
    },
    "monthly_credit_usage_limit": 100,
    "auto_recharge_enabled": true,
    "auto_recharge_threshold": 20,
    "auto_recharge_amount": 20,
    "allow_draw_organizations": true,
    "draw_organizations_limit_monthly": 100
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/me/settings
Summary: Update User Settings
Operation ID: `6931687e390a05e660e876d6e14e4563`

Description: Update your profile settings

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: UserSettingsRequest
  Type: object
  Properties:
    notifications:
      Type: App\Data\UserNotificationSettings
      allOf:
        # Schema: UserNotificationSettings
        Type: object
        Properties:
          new_phrase:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

          invitation:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

          added_to_entity:
            Type: array
            Items: 
              Type: string
              Example: "broadcast"

            Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


      Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

    monthly_credit_usage_limit:
      Type: number
      Format: float
      Description: The maximum amount that can be drawn from the monthly balance of the user.
      Example: 100

    auto_recharge_enabled:
      Type: boolean
      Default: false
      Description: Whether auto recharge is enabled for the user
      Example: true

    auto_recharge_threshold:
      Type: number
      Format: float
      Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
      Example: 20

    auto_recharge_amount:
      Type: number
      Format: float
      Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
      Example: 20

    allow_draw_organizations:
      Type: boolean
      Default: true
      Description: The allow draw organizations for the user
      Example: true

    draw_organizations_limit_monthly:
      Type: number
      Format: float
      Description: The draw organizations limit monthly for the user
      Example: 100



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: UserExtendedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      data:
        # Inferred schema: UserExtended
        # Schema: UserExtended
        Type: object
        Properties:
          id:
            Type: string
            Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

          firstname:
            Type: string
            Example: "Estelle"

          lastname:
            Type: string
            Example: "McLaughlin"

          email:
            Type: string
            Example: "schuppe.elmore@gmail.com"

          phone:
            Type: string
            Example: "(630) 622-5121"

          locale:
            Type: string
            Example: "es-cr"

          last_seen_at:
            Type: integer
            Description: Unix timestamp indicating last time the user interacted with the system.
            Example: 1764988634

          created_at:
            Type: integer
            Description: Unix timestamp indicating creation date.
            Example: 1764988634

          avatar:
            Type: App\Data\Avatar
            allOf:
              # Schema: Avatar
              Type: object
              Properties:
                width:
                  Type: integer
                  Example: 481

                height:
                  Type: integer
                  Example: 396

                original_url:
                  Type: string
                  Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

                thumb_url:
                  Type: string
                  Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

                medium_url:
                  Type: string
                  Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

                id:
                  Type: string
                  Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

                path:
                  Type: string
                  Description: Path of local file
                  Example: "/public/images"


            Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

          source_locales:
            Type: array
            Items: 
              Type: string
              Example: "en_MH"

            Description: List of locales user can translate from

          target_locales:
            Type: array
            Items: 
              Type: string
              Example: "ps_AF"

            Description: List of locales user can translate to

          settings:
            Type: App\Data\UserSettingsData
            allOf:
              # Schema: UserSettingsData
              Type: object
              Properties:
                notifications:
                  Type: App\Data\UserNotificationSettings
                  allOf:
                    # Schema: UserNotificationSettings
                    Type: object
                    Properties:
                      new_phrase:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      invitation:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

                      added_to_entity:
                        Type: array
                        Items: 
                          Type: string
                          Example: "broadcast"

                        Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.


                  Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

                monthly_credit_usage_limit:
                  Type: number
                  Format: float
                  Description: The maximum amount that can be drawn from the monthly balance of the user.
                  Example: 100

                auto_recharge_enabled:
                  Type: boolean
                  Default: false
                  Description: Whether auto recharge is enabled for the user
                  Example: true

                auto_recharge_threshold:
                  Type: number
                  Format: float
                  Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
                  Example: 20

                auto_recharge_amount:
                  Type: number
                  Format: float
                  Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
                  Example: 20

                allow_draw_organizations:
                  Type: boolean
                  Default: true
                  Description: The allow draw organizations for the user
                  Example: true

                draw_organizations_limit_monthly:
                  Type: number
                  Format: float
                  Description: The draw organizations limit monthly for the user
                  Example: 100





  Example Response:
```json
{
  "status": true,
  "data": {
    "id": "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc",
    "firstname": "Estelle",
    "lastname": "McLaughlin",
    "email": "schuppe.elmore@gmail.com",
    "phone": "(630) 622-5121",
    "locale": "es-cr",
    "last_seen_at": 1764988634,
    "created_at": 1764988634,
    "avatar": {
      "width": 481,
      "height": 396,
      "original_url": "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis",
      "thumb_url": "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html",
      "medium_url": "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio",
      "id": "1e7b475e-1319-4793-a944-45b45a5abc28",
      "path": "/public/images"
    },
    "source_locales": [
      "en_MH"
    ],
    "target_locales": [
      "ps_AF"
    ],
    "settings": {
      "notifications": {
        "new_phrase": [
          "broadcast"
        ],
        "invitation": [
          "broadcast"
        ],
        "added_to_entity": [
          "broadcast"
        ]
      },
      "monthly_credit_usage_limit": 100,
      "auto_recharge_enabled": true,
      "auto_recharge_threshold": 20,
      "auto_recharge_amount": 20,
      "allow_draw_organizations": true,
      "draw_organizations_limit_monthly": 100
    }
  }
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/me/email
Summary: Update User Email
Operation ID: `0636883f704e8747639bc017c7ec5efb`

Description: Update your email. This will send a verification email to your new email and switch it once the email is verified. 

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: EmailUpdateRequest
  Type: object
  Properties:
    email (Required):
      Type: string
      Example: "quinten.jaskolski@hotmail.com"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### PATCH /api/me/password
Summary: Update User Password
Operation ID: `5730d9adda89cc34895fbb8b04451f26`

Description: Update your password. 

Security Requirements:
- bearerAuth

Request Body:
Content-Type: `application/json`
Schema:
  # Schema: PasswordUpdateRequest
  Type: object
  Properties:
    password (Required):
      Type: string
      Example: "';4?5zo>bguL0%$=B'7"

    new_password (Required):
      Type: string
      Example: "BdlyiN21$!glld!"

    new_password_confirmation (Required):
      Type: string
      Example: "BdlyiN21$!glld!"



Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: OK
    Type: object
    Properties:
      status:
        Type: boolean
        Default: true
        Description: Success


  Example Response:
```json
{
  "status": true
}
```
- 401: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNAUTHORIZED_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unauthorized"
        Description: Error description

      code:
        Type: integer
        Default: 401
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```
- 422: error
  Content-Type: `application/json`
  Schema:
    # Schema: UNPROCESSABLE_ENTITY_ERROR
    Type: object
    Properties:
      error:
        Type: string
        Default: "Unprocessable Entity"
        Description: Error description

      code:
        Type: integer
        Default: 422
        Description: Error Code


  Example Response:
```json
{
  "error": "string",
  "code": 1
}
```

---

#### GET /api/me/invitations
Summary: List User Invitations
Operation ID: `53c209b6dbc08781a056b43d481832d9`

Description: Get all the active - non expired invitations the authenticated user has available. Pagination parameters are optional.

Security Requirements:
- bearerAuth

Parameters:
- `page` in query: Page to request
  Type: integer
  Example: `"1"`
- `records_per_page` in query: Number of records per page
  Type: integer
  Example: `"10"`
- `order_by` in query: Order results by specified field(s). Supports single field (order_by=field:direction) or multiple fields for tie-breaking (order_by[]=field1:direction&order_by[]=field2:direction) <br><br>[View orderable fields and defaults](/documentation/ordering)
  Type: Composition (one of)
  Example: `"created_at:desc"`
- `filter_by` in query: Filter results by field values. Supports single filter (filter_by=field:value) or multiple filters (filter_by[]=field1:value&filter_by[]=field2:value) <br><br>[View filterable fields and defaults](/documentation/filtering)
  Type: Composition (one of)
  Example: `"filter_by[]=status:active&filter_by[]=type:premium"`

Responses:
- 200: success
  Content-Type: `application/json`
  Schema:
    # Schema: InvitationPaginatedResponse
    Type: object
    Properties:
      status:
        Type: boolean
        Description: Response status
        Example: true

      page:
        Type: integer
        Description: Current page number
        Example: 1

      records_per_page:
        Type: integer
        Description: Number of records per page
        Example: 8

      page_count:
        Type: integer
        Description: Number of pages
        Example: 5

      total_records:
        Type: integer
        Description: Total number of items
        Example: 40

      data:
        Type: array
        Items: 
          allOf:
            # Schema: Invitation
            Type: object
            Properties:
              id:
                Type: string
                Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

              inviter_id:
                Type: string
                Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

              inviter:
                Type: string
                Example: "John Doe"

              invitee_id:
                Type: string
                Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

              invitee:
                Type: string
                Example: "John Miles"

              email:
                Type: string
                Example: "clement.terry@hotmail.com"

              entity_id:
                Type: string
                Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

              entity_type:
                Type: string
                Example: "Organization"

              entity_name:
                Type: string
                Example: "Flexmark"

              role:
                Type: string
                Example: "organization_admin"

              expires_at:
                Type: integer
                Example: 1764988634



        Description: List of items


  Example Response:
```json
{
  "status": true,
  "page": 1,
  "records_per_page": 8,
  "page_count": 5,
  "total_records": 40,
  "data": [
    {
      "id": "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2",
      "inviter_id": "376fe412-16e7-4aaa-8c29-204a62f62067",
      "inviter": "John Doe",
      "invitee_id": "8ba13acb-e98a-4f17-bcaf-798ceee4b924",
      "invitee": "John Miles",
      "email": "clement.terry@hotmail.com",
      "entity_id": "58854932-093b-4183-9ea7-ef29dcc2fa07",
      "entity_type": "Organization",
      "entity_name": "Flexmark",
      "role": "organization_admin",
      "expires_at": 1764988634
    }
  ]
}
```

---

## Schemas

### OK

Type: object
Properties:
  status:
    Type: boolean
    Default: true
    Description: Success



### ArrayOfIdsResponse

Type: object
Properties:
  status:
    Type: boolean
    Default: true
    Description: Success

  data:
    Type: array
    Items: 
      Type: string
      Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

    Description: Deleted IDs



### UNAUTHORIZED_ERROR

Type: object
Properties:
  error:
    Type: string
    Default: "Unauthorized"
    Description: Error description

  code:
    Type: integer
    Default: 401
    Description: Error Code



### UNPROCESSABLE_ENTITY_ERROR

Type: object
Properties:
  error:
    Type: string
    Default: "Unprocessable Entity"
    Description: Error description

  code:
    Type: integer
    Default: 422
    Description: Error Code



### NOT_FOUND_ENTITY_ERROR

Type: object
Properties:
  error:
    Type: string
    Default: "Entity not found"
    Description: Error description

  code:
    Type: integer
    Default: 404
    Description: Error Code



### InsertedIdResponse

Type: object
Properties:
  status:
    Type: boolean
    Default: "true"
    Description: Response status

  data:
    Type: object
    Properties:
      id:
        Type: string
        Format: uuid
        Example: "807adb43-5439-4135-b45c-06d0c053492f"




### DeprecatedLocaleCategorizedResponse

Type: object
allOf:
  Reference to: `InsertedIdResponse`
  Type: object
  Properties:
    status:
      Type: boolean
      Default: "true"
      Description: Response status

    data:
      Type: object
      Properties:
        Spanish:
          Type: array
          Items: 
            Type: object
            Properties:
              code:
                Type: string
                Description: Locale code
                Example: "es-cr"

              name:
                Type: string
                Description: Locale name
                Example: "Spanish (Costa Rica)"







### LocaleCategorizedResponse

Type: object
Properties:
  status:
    Type: boolean
    Default: true
    Description: Response status

  data:
    Type: object
    Properties:
      en-us:
        Type: object
        Properties:
          Spanish:
            Type: array
            Items: 
              Type: object
              Properties:
                code:
                  Type: string
                  Description: Locale code
                  Example: "es-cr"

                name:
                  Type: string
                  Description: Locale name
                  Example: "Spanish (Costa Rica)"







### ManualLocaleFlatResponse

Type: object
Properties:
  status:
    Type: boolean
    Default: true
    Description: Response status

  data:
    Type: object
    Properties:
      en-us:
        Type: array
        Items: 
          allOf:
            Reference to: `LocaleFlat`





### ManualLocaleDataResponse

Type: object
Properties:
  status:
    Type: boolean
    Default: true
    Description: Response status

  data:
    Type: object
    Properties:
      en-us:
        Type: array
        Items: 
          allOf:
            Reference to: `Locale`





### SimplePhraseResponse

Type: object
Properties:
  status:
    Type: boolean
    Default: "true"
    Description: Response status

  data:
    Type: array
    Items: 
      Type: string
      Example: "{[UI]} Home"




### TranslationMeta

Type: object
Properties:
  words:
    Type: integer
    Description: Number of translatable words in project..
    Example: "752"

  untranslated:
    Type: integer
    Description: Number of words in project that are yet translated.
    Example: "25"



### TranslationDataNullCategory

Type: object
allOf:
  Reference to: `TranslationWithPhrase`
  Type: object
  Properties:
    category:
      Type: string
      Description: Token context category.
      Example: null




### TranslationDataManualResponse

Type: object
allOf:
  Reference to: `OK`
  Reference to: `TranslationMeta`
  Type: object
  Properties:
    data:
      Type: object
      Properties:
        UI:
          Type: array
          Items: 
            allOf:
              Reference to: `TranslatableItemTranslations`

          Description: Token category





### TranslationManualResponse

Type: object
allOf:
  Reference to: `OK`
  Reference to: `TranslationMeta`
  Type: object
  Properties:
    data:
      Type: object
      Properties:
        UI:
          Type: object
          Properties:
            Home:
              Type: string
              Description: Translation indexed by token inside token category.
              Example: "Inicio"

            aDcac8503LPQR:
              Type: object
              Properties:
                Content block phrase 1:
                  Type: string
                  Description: Translation indexed by token inside token category.
                  Example: "Frase de bloque de contenido 1"

                Content block phrase 2:
                  Type: string
                  Description: Translation indexed by token inside token category.
                  Example: "Frase de bloque de contenido 2"

              Description: Content block custom_id

          Description: Token category

        __uncategorized__:
          Type: object
          Properties:
            Home:
              Type: string
              Description: Translation indexed by token inside token category.
              Example: "Hogar"

          Description: Default category for uncategorized tokens





### MachineTranslateTranslatableItemsManualResponse

Type: object
allOf:
  Reference to: `OK`
  Type: object
  Properties:
    data:
      Type: object
      Properties:
        es-cr:
          Type: array
          Items: 
            allOf:
              Reference to: `TranslatableItemTranslations`

          Description: Requested target locales





### ContentBlockData

Type: object
Properties:
  id:
    Type: string
    Description: Unique identifier. This is the same as the content block id, but declared because of the fact that we are mixing entity, so we need a unique uniform id property for both content blocks and single translations.
    Example: "01cce290-c475-33e7-83fa-b5ed4957faaf"

  translation_id:
    Type: string
    Example: null

  content_block_id:
    Type: string
    Example: "01cce290-c475-33e7-83fa-b5ed4957faaf"

  custom_id:
    Type: string
    Example: "6796dbe5-8dd4-323b-bb6c-a4dbd2ebc70e"

  category:
    Type: string
    Example: "UI"

  translations:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`


  label:
    Type: string
    Example: "Home"

  untranslated:
    Type: boolean
    Example: true

  translatable:
    Type: boolean
    Example: true

  memory_translated:
    Type: boolean
    Example: true



### ContentBlockResponseManual

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ContentBlockData`
    Description: Response payload



### NotificationPaginatedResponseWithMeta

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  page_count:
    Type: integer
    Description: Total number of pages
    Example: 5

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  total_records:
    Type: integer
    Description: Total number of records
    Example: 40

  read:
    Type: integer
    Description: Number of read notifications
    Example: 4

  unread:
    Type: integer
    Description: Number of unread notifications
    Example: 3

  data:
    Type: array
    Items: 
      Reference to: `Notification`
    Description: Array of notifications



### DeletedTranslation

Type: object
Properties:
  id:
    Type: string
    Example: "a1d97633-a1cb-436b-9390-a8155896b5ae"

  locale:
    Type: string
    Description: Translation locale.
    Example: "es-es"

  category:
    Type: string
    Description: Phrase context category.
    Example: "UI"

  phrase:
    Type: string
    Example: "Hello"

  phrase_id:
    Type: string
    Example: "83df7761-4e3e-4d1f-9f60-cf1e47ef962f"

  translation:
    Type: string
    Description: Translation text in the locale provided in this response.
    Example: "Hola"

  translatable:
    Type: boolean
    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
    Example: true

  memory_translation:
    Type: boolean
    Example: true

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634



### DeletedTranslationResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `DeletedTranslation`
    Description: Response payload



### DeletedTranslationPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DeletedTranslation`

    Description: List of items



### DeletedTranslationListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DeletedTranslation`

    Description: List of items



### UserProjectSettings

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



### UserProjectSettingsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserProjectSettings`
    Description: Response payload



### UserProjectSettingsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserProjectSettings`

    Description: List of items



### UserProjectSettingsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserProjectSettings`

    Description: List of items



### MachineTranslation

Type: object
Properties:
  translations:
    Type: array
    Items: 
      Type: string
      Example: "Texto traducido"

    Description: Array of translated strings

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Description: Machine translator used
    Example: "deepl"



### MachineTranslationResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `MachineTranslation`
    Description: Response payload



### MachineTranslationPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslation`

    Description: List of items



### MachineTranslationListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslation`

    Description: List of items



### OrganizationSettings

Type: object
Properties:
  use_translation_memory:
    Type: boolean
    Default: true
    Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
    Example: true

  machine_translate_new_phrases:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
    Example: true

  use_machine_translations:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
    Example: true

  translate_base_locale_only:
    Type: boolean
    Default: false
    Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
    Example: true

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Organization wide setting that determines the default machine translator to use in the projects.
    Example: "deepl"

  broadcast_translations:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
    Example: true

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: Organization wide setting that determines the monthly usage limit for the organization.
    Example: 20

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: Organization wide setting that determines the threshold for automatic recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: Organization wide setting that determines the amount to recharge.
    Example: 20

  auto_recharge_source:
    Type: enum
    Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
    Default: "account_balance_or_credit_card"
    Description: Organization wide setting that determines the source of the automatic recharge.
    Example: "credit_card"

  allow_draw_projects:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
    Example: true

  draw_projects_limit_monthly:
    Type: number
    Format: float
    Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
    Example: 20



### OrganizationSettingsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `OrganizationSettings`
    Description: Response payload



### OrganizationSettingsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `OrganizationSettings`

    Description: List of items



### OrganizationSettingsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `OrganizationSettings`

    Description: List of items



### MachineTranslationSummary

Type: object
Properties:
  total_phrases:
    Type: integer
    Description: Total number of phrases translated.
    Example: 130

  total_words:
    Type: integer
    Description: Total number of words translated.
    Example: 1520

  billing_amount:
    Type: number
    Format: float
    Description: Total billing amount of translations.
    Example: 11520



### MachineTranslationSummaryResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `MachineTranslationSummary`
    Description: Response payload



### MachineTranslationSummaryPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslationSummary`

    Description: List of items



### MachineTranslationSummaryListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslationSummary`

    Description: List of items



### ApiKey

Type: object
Properties:
  id:
    Type: string
    Example: "b5afd523-e8b8-4d4c-a27e-2cc2abe1c95d"

  name:
    Type: string
    Example: "Langsys Production Api Key"

  description:
    Type: string
    Description: Description of the API key
    Example: "This API key is used for production environment"

  type:
    Type: enum
    Enum: ["read", "write"]
    Example: "write"

  active:
    Type: boolean
    Example: true

  created_at:
    Type: integer
    Description: Unix timestamp of when the api key was created.
    Example: 1764988634

  updated_at:
    Type: integer
    Description: Unix timestamp of when the api key was last updated.
    Example: 1764988634

  last_used_at:
    Type: integer
    Description: Unix timestamp of when the api key was last used.
    Example: 1764988634

  project:
    Type: App\Data\ProjectBasic
    allOf:
      Reference to: `ProjectBasic`
    Description: Details of the associated project.

  organization:
    Type: App\Data\OrganizationBasic
    allOf:
      Reference to: `OrganizationBasic`
    Description: Details of the associated organization.



### ApiKeyResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ApiKey`
    Description: Response payload



### ApiKeyPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ApiKey`

    Description: List of items



### ApiKeyListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ApiKey`

    Description: List of items



### FullContentBlock

Type: object
Properties:
  id:
    Type: string
    Example: "9f2e9717-92c2-42f7-b2f7-de42d57c78cb"

  translation_id:
    Type: string
    Example: "be08f03f-d355-49fd-8f5d-f854f41f1ddb"

  content_block_id:
    Type: string
    Example: "f02dc349-3e38-49c4-98f1-f76afc5370ec"

  custom_id:
    Type: string
    Example: "09dcbd8f-cfd2-4b43-935a-1a45f8d3ecf2"

  category:
    Type: string
    Example: "UI"

  content:
    Type: string
    Example: "<ul><li>Home</li></ul>"

  translations:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`


  label:
    Type: string
    Example: "Home"

  untranslated:
    Type: boolean
    Default: false
    Example: true

  translatable:
    Type: boolean
    Default: false
    Example: true

  memory_translated:
    Type: boolean
    Default: false
    Example: true

  ai_translated:
    Type: boolean
    Default: false
    Example: false



### FullContentBlockResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `FullContentBlock`
    Description: Response payload



### FullContentBlockPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `FullContentBlock`

    Description: List of items



### FullContentBlockListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `FullContentBlock`

    Description: List of items



### PhraseSimple

Type: object
Properties:
  id:
    Type: string
    Description: The unique identifier of the phrase
    Example: "phrase-id-123"

  phrase:
    Type: string
    Description: The phrase text content
    Example: "Welcome to our application"

  category:
    Type: string
    Description: The category of the phrase
    Example: "ui"

  translatable:
    Type: boolean
    Description: Whether the phrase is translatable
    Example: true

  words:
    Type: integer
    Example: 1



### PhraseSimpleResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `PhraseSimple`
    Description: Response payload



### PhraseSimplePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `PhraseSimple`

    Description: List of items



### PhraseSimpleListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `PhraseSimple`

    Description: List of items



### UserOrganizationAccess

Type: object
Properties:
  id:
    Type: string
    Example: "8c07c1b3-5dc8-4829-97a3-49b56b235b0c"

  firstname:
    Type: string
    Example: "Cleora"

  lastname:
    Type: string
    Example: "Hammes"

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`

  last_activity_at:
    Type: integer
    Example: 1740633734

  role:
    Type: App\Data\RoleData
    allOf:
      Reference to: `RoleData`

  invited_by:
    Type: App\Data\UserData
    allOf:
      Reference to: `UserData`



### UserOrganizationAccessResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserOrganizationAccess`
    Description: Response payload



### UserOrganizationAccessPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserOrganizationAccess`

    Description: List of items



### UserOrganizationAccessListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserOrganizationAccess`

    Description: List of items



### ApiKeyProject

Type: object
Properties:
  id:
    Type: string
    Example: "980209f5-b8ba-4b67-b96a-c47cdc7a8e97"

  title:
    Type: string
    Example: "Comercado"

  base_locale:
    Type: string
    Description: Locale in which project phrase strings are written.
    Example: "en-us"

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "fr-ca"

    Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

  default_locales:
    Type: array
    Items: 
      Type: string
      Example: "es-cr"

    Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

  key_type:
    Type: enum
    Enum: ["read", "write"]
    Description: Type of API key
    Example: "write"



### ApiKeyProjectResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ApiKeyProject`
    Description: Response payload



### ApiKeyProjectPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ApiKeyProject`

    Description: List of items



### ApiKeyProjectListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ApiKeyProject`

    Description: List of items



### ProjectTotals

Type: object
Properties:
  phrases:
    Type: integer
    Description: Total number of phrases in project.
    Example: 291

  words:
    Type: integer
    Description: Total number of words in project.
    Example: 755

  words_to_translate:
    Type: integer
    Description: Total number of words to translate in project. This is equivalent to words * target_locales.
    Example: 3020

  target_locales:
    Type: integer
    Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
    Example: 4

  translated_target_locales:
    Type: integer
    Description: Total number of locales for which translations exist.
    Example: 3

  translations:
    Type: App\Data\TranslationTotals\GlobalTranslationTotals
    allOf:
      Reference to: `GlobalTranslationTotals`
    Description: Translations totals further separated into human vs ai, and also grouped by locale.

  my_translations:
    Type: App\Data\TranslationTotals\TotalsWithLocales
    allOf:
      Reference to: `TotalsWithLocales`
    Description: Translations made by user in the project and also grouped by locale.

  untranslated:
    Type: App\Data\TranslationTotals\TotalsWithLocales
    allOf:
      Reference to: `TotalsWithLocales`
    Description: Untranslated phrases in project and also grouped by locale.



### ProjectTotalsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ProjectTotals`
    Description: Response payload



### ProjectTotalsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ProjectTotals`

    Description: List of items



### ProjectTotalsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ProjectTotals`

    Description: List of items



### ApiKeyCreated

Type: object
Properties:
  id:
    Type: string
    Example: "979d8e68-620b-4805-a946-c5dbf40d724e"

  name:
    Type: string
    Example: "Langsys Production Api Key"

  description:
    Type: string
    Description: Description of the API key
    Example: "This API key is used for production environment"

  type:
    Type: enum
    Enum: ["read", "write"]
    Example: "read"

  active:
    Type: boolean
    Example: true

  key:
    Type: string
    Description: The actual API key value
    Example: "l8hqXC29KVUJamjxTV2nRSwEh0PyYiucf3UCOUZ6elL54AcrHsbI4YAXFA59Gdf2"

  created_at:
    Type: integer
    Description: Unix timestamp of when the api key was created.
    Example: 1764988634

  updated_at:
    Type: integer
    Description: Unix timestamp of when the api key was last updated.
    Example: 1764988634

  last_used_at:
    Type: integer
    Description: Unix timestamp of when the api key was last used.
    Example: 1764988634

  project:
    Type: App\Data\ProjectBasic
    allOf:
      Reference to: `ProjectBasic`
    Description: Details of the associated project.

  organization:
    Type: App\Data\OrganizationBasic
    allOf:
      Reference to: `OrganizationBasic`
    Description: Details of the associated organization.



### ApiKeyCreatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ApiKeyCreated`
    Description: Response payload



### ApiKeyCreatedPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ApiKeyCreated`

    Description: List of items



### ApiKeyCreatedListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ApiKeyCreated`

    Description: List of items



### Balance

Type: object
Properties:
  total_balance:
    Type: number
    Format: float
    Description: Total balance.
    Example: 100

  prepaid_credits_balance:
    Type: number
    Format: float
    Description: Prepaid credits balance.
    Example: 50

  free_credits_balance:
    Type: number
    Format: float
    Description: Free credits balance.
    Example: 50



### BalanceResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Balance`
    Description: Response payload



### BalancePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Balance`

    Description: List of items



### BalanceListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Balance`

    Description: List of items



### ImageKit

Type: object
Properties:
  token:
    Type: string
    Example: "76b3e92c-96d7-4a24-a8d6-2e3cf048170c"

  expire:
    Type: integer
    Example: 1693025644

  signature:
    Type: string
    Example: "6466363c92c0cfc572c8c3d5f1bdeac2d52d827e"



### ImageKitResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ImageKit`
    Description: Response payload



### ImageKitPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ImageKit`

    Description: List of items



### ImageKitListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ImageKit`

    Description: List of items



### BalanceTransfer

Type: object
Properties:
  source_balance:
    Type: App\Http\Resources\BalanceResource
    allOf:
      Reference to: `Balance`
    Description: Balance information for the source entity after transfer

  destination_balance:
    Type: App\Http\Resources\BalanceResource
    allOf:
      Reference to: `Balance`
    Description: Balance information for the destination entity after transfer



### BalanceTransferResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `BalanceTransfer`
    Description: Response payload



### BalanceTransferPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `BalanceTransfer`

    Description: List of items



### BalanceTransferListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `BalanceTransfer`

    Description: List of items



### DialCode

Type: object
Properties:
  country_code:
    Type: string
    Description: Country Code
    Example: "CR"

  dial_code:
    Type: string
    Description: Dial code
    Example: "506"

  name:
    Type: string
    Description: The country name and the dial code
    Example: "Costa Rica (+506)"



### DialCodeResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `DialCode`
    Description: Response payload



### DialCodePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DialCode`

    Description: List of items



### DialCodeListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DialCode`

    Description: List of items



### TranslatableItemTranslations

Type: object
Properties:
  id:
    Type: string
    Example: "9c99afd7-38ec-42e0-97fd-da626eeff08a"

  project_id:
    Type: string
    Example: "d951ca8f-8e6f-4d62-b47a-3de9000392dd"

  label:
    Type: string
    Description: Sanitized phrase truncated to 25 chars.
    Example: "About"

  locale:
    Type: string
    Example: "es-es"

  category:
    Type: string
    Description: Phrase or content block context category.
    Example: "UI"

  type:
    Type: enum
    Enum: ["phrase", "content_block"]
    Example: "phrase"

  phrase_id:
    Type: string
    Description: Phrase id. This field will be null if the request is for a content block.
    Example: "e21c852c-99c0-42a7-be81-767716560693"

  phrase:
    Type: string
    Description: Phrase text. This field will be null if the request is for a content block.
    Example: "About"

  translation_id:
    Type: string
    Description: This field will be null if the request is for a content block.
    Example: "8c7d0ab7-b54c-428e-8ce8-42bce0caad08"

  translation:
    Type: string
    Description: Translation text in the locale requested. This field will be null if the request is for a content block.
    Example: "Nosotros"

  translator:
    Type: App\Http\Resources\UserSimpleResource
    allOf:
      Reference to: `UserSimple`
    Description: User that translated the phrase in case it was translated by a human.

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Machine translator used to translate the phrase.
    Example: "xai"

  content_block_id:
    Type: string
    Description: This field will be null if the translatable item is a phrase.
    Example: "6c2dce67-b078-40c2-9111-ee9dae1c686b"

  custom_id:
    Type: string
    Description: Custom id for content block. This field will be null if the translatable item is a phrase.
    Example: "blE14pfd1$"

  content:
    Type: string
    Description: Content block html content. This field will be null if the translatable item is a phrase.
    Example: "<p>About <strong>us</strong></p>"

  translations:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`

    Description: List of translations for content block. This field will be null if the request is for a single phrase.

  words:
    Type: integer
    Default: 0
    Example: 1

  untranslated:
    Type: boolean
    Default: false
    Example: true

  translatable:
    Type: boolean
    Default: false
    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
    Example: true

  restorable:
    Type: boolean
    Default: false
    Description: Whether this phrase is able to be restored after being marked as untranslatable.
    Example: false

  human_translated:
    Type: boolean
    Default: false
    Description: Whether translation was done by a human.
    Example: true

  memory_translated:
    Type: boolean
    Default: false
    Description: Whether translation comes from translation memory.
    Example: true

  ai_translated:
    Type: boolean
    Default: false
    Description: Whether translation is translated by AI.
    Example: false

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634



### TranslatableItemTranslationsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `TranslatableItemTranslations`
    Description: Response payload



### TranslatableItemTranslationsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslatableItemTranslations`

    Description: List of items



### TranslatableItemTranslationsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslatableItemTranslations`

    Description: List of items



### Invitation

Type: object
Properties:
  id:
    Type: string
    Example: "6f29f6c4-6fe7-4653-a198-80c1a21ccbf2"

  inviter_id:
    Type: string
    Example: "376fe412-16e7-4aaa-8c29-204a62f62067"

  inviter:
    Type: string
    Example: "John Doe"

  invitee_id:
    Type: string
    Example: "8ba13acb-e98a-4f17-bcaf-798ceee4b924"

  invitee:
    Type: string
    Example: "John Miles"

  email:
    Type: string
    Example: "clement.terry@hotmail.com"

  entity_id:
    Type: string
    Example: "58854932-093b-4183-9ea7-ef29dcc2fa07"

  entity_type:
    Type: string
    Example: "Organization"

  entity_name:
    Type: string
    Example: "Flexmark"

  role:
    Type: string
    Example: "organization_admin"

  expires_at:
    Type: integer
    Example: 1764988634



### InvitationResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Invitation`
    Description: Response payload



### InvitationPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Invitation`

    Description: List of items



### InvitationListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Invitation`

    Description: List of items



### ActivitySummary

Type: object
Properties:
  get_requests:
    Type: integer
    Description: Total number of get requests.
    Example: 130

  post_requests:
    Type: integer
    Description: Total number of post requests.
    Example: 130

  patch_requests:
    Type: integer
    Description: Total number of patch requests.
    Example: 130

  delete_requests:
    Type: integer
    Description: Total number of delete requests.
    Example: 130



### ActivitySummaryResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ActivitySummary`
    Description: Response payload



### ActivitySummaryPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ActivitySummary`

    Description: List of items



### ActivitySummaryListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ActivitySummary`

    Description: List of items



### UserSubscription

Type: object
Properties:
  id:
    Type: string
    Description: Subscription ID
    Example: "591f061f-7044-4f19-bb55-5e71d0ee338b"

  user_id:
    Type: string
    Description: User ID
    Example: "4ac3051e-a84a-4cc9-b721-207916d08e32"

  plan_cycle_id:
    Type: string
    Description: Plan cycle ID
    Example: "5ce38c12-d6b0-4e29-9201-4526e96a96c7"

  status:
    Type: string
    Description: Subscription status
    Example: ""

  error:
    Type: string
    Description: Error message
    Example: ""



### UserSubscriptionResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserSubscription`
    Description: Response payload



### UserSubscriptionPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserSubscription`

    Description: List of items



### UserSubscriptionListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserSubscription`

    Description: List of items



### MachineTranslationTransaction

Type: object
Properties:
  id:
    Type: string
    Example: "ba98bde6-3552-454d-903b-6907795f48e4"

  phrase_id:
    Type: string
    Description: Phrase ID.
    Example: "2c558493-3b75-44fb-952d-bcb40b177a45"

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Description: Machine translation service.
    Example: "deepl"

  transaction_type:
    Type: enum
    Enum: ["translation", "detection"]
    Description: Machine translation transaction type.
    Example: "detection"

  user_id:
    Type: string
    Description: User that executed the machine translation.
    Example: "1f081808-cb7a-49be-bd2c-ab6b96e43c39"

  locale_code:
    Type: string
    Description: Machine translation locale.
    Example: "es-es"

  phrases:
    Type: integer
    Description: Total number of phrases translated in transaction.
    Example: 13

  words:
    Type: integer
    Description: Total number of words translated in transaction.
    Example: 152

  billing_amount:
    Type: number
    Format: float
    Description: Total billing amount for transaction.
    Example: 152

  date:
    Type: integer
    Description: Machine translation transaction date.
    Example: 1764988634



### MachineTranslationTransactionResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `MachineTranslationTransaction`
    Description: Response payload



### MachineTranslationTransactionPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslationTransaction`

    Description: List of items



### MachineTranslationTransactionListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslationTransaction`

    Description: List of items



### MachineTranslator

Type: object
Properties:
  value:
    Type: string
    Description: Handle to use when selecting the service.
    Example: "chatgpt4o"

  label:
    Type: string
    Example: "ChatGPT 4o"

  translation_billing_rate:
    Type: number
    Format: float
    Description: Machine translation service rate per million characters.
    Example: 60

  language_detection_billing_rate:
    Type: number
    Format: float
    Description: Machine translation service rate per million characters.
    Example: 60



### MachineTranslatorResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `MachineTranslator`
    Description: Response payload



### MachineTranslatorPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslator`

    Description: List of items



### MachineTranslatorListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `MachineTranslator`

    Description: List of items



### TranslatableItem

Type: object
Properties:
  id:
    Type: string
    Description: Translatable item ID
    Example: "123e4567-e89b-12d3-a456-426614174000"

  project_id:
    Type: string
    Description: Project ID
    Example: "123e4567-e89b-12d3-a456-426614174001"

  label:
    Type: string
    Description: Sanitized phrase truncated to 25 chars.
    Example: "About"

  category:
    Type: string
    Description: Phrase or content block context category.
    Example: "UI"

  type:
    Type: enum
    Enum: ["phrase", "content_block"]
    Description: Type of translatable item
    Example: "phrase"

  phrase_id:
    Type: string
    Description: Phrase id. This field will be null if the item is a content block.
    Example: "c28e222f-afe1-471b-916b-89d83cb234f4"

  phrase:
    Type: string
    Description: Phrase text. This field will be null if the item is a content block.
    Example: "About"

  content_block_id:
    Type: string
    Description: Content block id. This field will be null if the item is a phrase.
    Example: "6c58fb0b-37c4-4909-9c16-c4b5becfd14e"

  custom_id:
    Type: string
    Description: Custom id for content block. This field will be null if the item is a phrase.
    Example: "blE14pfd1$"

  phrases:
    Type: array
    Items: 
      allOf:
        Reference to: `PhraseSimple`

    Description: List of phrases for content block. This field will be null if the item is a phrase.

  words:
    Type: integer
    Default: 0
    Example: 1

  translatable:
    Type: boolean
    Default: false
    Description: Whether phrase is translatable to other languages.
    Example: true

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634



### TranslatableItemResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `TranslatableItem`
    Description: Response payload



### TranslatableItemPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslatableItem`

    Description: List of items



### TranslatableItemListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslatableItem`

    Description: List of items



### ProjectSettings

Type: object
Properties:
  use_translation_memory:
    Type: boolean
    Default: true
    Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
    Example: true

  machine_translate_new_phrases:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
    Example: true

  use_machine_translations:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
    Example: true

  translate_base_locale_only:
    Type: boolean
    Default: false
    Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
    Example: true

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Project wide setting that determines which machine translator to use.
    Example: "deepl"

  broadcast_translations:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
    Example: true

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: Project wide setting that determines the monthly usage limit for the project.
    Example: 20

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: Project wide setting that determines the threshold for automatic recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: Project wide setting that determines the amount to recharge.
    Example: 20

  auto_recharge_source:
    Type: enum
    Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
    Default: "organization_balance_or_credit_card"
    Description: Project wide setting that determines the source of the automatic recharge.
    Example: "organization_balance"



### ProjectSettingsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ProjectSettings`
    Description: Response payload



### ProjectSettingsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ProjectSettings`

    Description: List of items



### ProjectSettingsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ProjectSettings`

    Description: List of items



### SingleTranslationOrContentBlock

Type: object
Properties:
  id:
    Type: string
    Example: "bbc33428-9b44-485c-8055-b86a202acbc4"

  label:
    Type: string
    Description: Sanitized phrase truncated to 25 chars.
    Example: "About"

  locale:
    Type: string
    Example: "es-es"

  category:
    Type: string
    Description: Phrase or content block context category.
    Example: "UI"

  translation_id:
    Type: string
    Description: This field will be null if the request is for a content block.
    Example: "946972d2-a21d-48c3-8473-b69ddd101101"

  content_block_id:
    Type: string
    Description: This field will be null if the request is for a single phrase.
    Example: "956b274d-fcf5-4390-8fdd-a89afa0d73b9"

  phrase:
    Type: string
    Description: Phrase text. This field will be null if the request is for a content block.
    Example: "About"

  phrase_id:
    Type: string
    Description: Phrase id. This field will be null if the request is for a content block.
    Example: "0b5857d0-2fe2-4047-ab17-a2a3479de60b"

  translation:
    Type: string
    Description: Translation text in the locale requested. This field will be null if the request is for a content block.
    Example: "Nosotros"

  words:
    Type: integer
    Default: 0
    Example: 1

  translations:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`

    Description: List of translations for content block. This field will be null if the request is for a single phrase.

  untranslated:
    Type: boolean
    Default: false
    Example: true

  translatable:
    Type: boolean
    Default: false
    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
    Example: true

  restorable:
    Type: boolean
    Default: false
    Description: Whether this phrase is able to be restored after being marked as untranslatable.
    Example: false

  human_translated:
    Type: boolean
    Default: false
    Description: Whether translation was done by a human.
    Example: true

  memory_translated:
    Type: boolean
    Default: false
    Description: Whether translation comes from translation memory.
    Example: true

  ai_translated:
    Type: boolean
    Default: false
    Description: Whether translation is translated by AI.
    Example: false



### SingleTranslationOrContentBlockResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `SingleTranslationOrContentBlock`
    Description: Response payload



### SingleTranslationOrContentBlockPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `SingleTranslationOrContentBlock`

    Description: List of items



### SingleTranslationOrContentBlockListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `SingleTranslationOrContentBlock`

    Description: List of items



### UserOrganizationSettings

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



### UserOrganizationSettingsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserOrganizationSettings`
    Description: Response payload



### UserOrganizationSettingsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserOrganizationSettings`

    Description: List of items



### UserOrganizationSettingsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserOrganizationSettings`

    Description: List of items



### BalanceTransaction

Type: object
Properties:
  id:
    Type: string
    Description: Transaction ID
    Example: "123e4567-e89b-12d3-a456-426614174001"

  entity_id:
    Type: string
    Description: Entity ID
    Example: "123e4567-e89b-12d3-a456-426614174000"

  entity_type:
    Type: string
    Description: Entity type (user, organization, project)
    Example: "user"

  amount:
    Type: number
    Format: float
    Description: Transaction amount
    Example: 50

  type:
    Type: enum
    Enum: ["credit", "auto_recharge", "machine_translation", "draw_from_account", "draw_from_organization", "prepaid_credits_invoiced", "free_credits_granted", "prepaid_credits_transfer", "free_credits_transfer"]
    Description: Transaction type
    Example: "prepaid_credits_transfer"

  balance_before:
    Type: number
    Format: float
    Description: Balance before transaction
    Example: 50

  balance_after:
    Type: number
    Format: float
    Description: Balance after transaction
    Example: 50

  prepaid_credit:
    Type: boolean
    Description: Pre-paid credit
    Example: true

  reference_entity_id:
    Type: string
    Description: Reference Entity ID
    Example: "ref_123"

  reference_entity_type:
    Type: string
    Description: Reference Entity Type
    Example: "user"

  payment_provider:
    Type: enum
    Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
    Description: Payment provider
    Example: "authorize_net"

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Description: Machine translator used in Transaction
    Example: "google"

  created_at:
    Type: integer
    Description: Transaction date
    Example: 1764988634



### BalanceTransactionResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `BalanceTransaction`
    Description: Response payload



### BalanceTransactionPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `BalanceTransaction`

    Description: List of items



### BalanceTransactionListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `BalanceTransaction`

    Description: List of items



### Subscription

Type: object
Properties:
  id:
    Type: string
    Description: Subscription ID
    Example: "337c91be-72e4-461d-8810-4934ea433a66"

  user_id:
    Type: string
    Description: User ID associated with the subscription
    Example: "82665bf0-ec00-403c-adb0-fd2ac6ad584b"

  plan_cycle_id:
    Type: string
    Description: Plan cycle ID associated with the subscription
    Example: "f6889c6d-c8f6-3ec2-b557-dc6dafd60dee"

  plan_type:
    Type: enum
    Enum: ["free", "business", "enterprise"]
    Description: Type of the plan
    Example: "enterprise"

  plan_cycle:
    Type: enum
    Enum: ["monthly", "yearly", "lifetime"]
    Description: Cycle of the plan
    Example: "lifetime"

  status:
    Type: string
    Description: Status of the subscription
    Example: "active"

  created_at:
    Type: string
    Description: Created at
    Example: "2024-07-01"

  api_usage_units_used:
    Type: integer
    Description: API usage units used
    Example: 100

  invoice_url:
    Type: string
    Description: Invoice URL
    Example: "https://ik.imagekit.io/dk8tdco09/langsys/invoices/09b3b92a-3399-4a3f-a121-afbd89a75d22/billing/LANGSYS_INV_20250409_147625.pdf"

  next_billing_date:
    Type: string
    Description: Next billing date
    Example: "2024-07-01"

  expiration_date:
    Type: string
    Description: Expiration date
    Example: "2024-07-01"



### SubscriptionResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Subscription`
    Description: Response payload



### SubscriptionPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Subscription`

    Description: List of items



### SubscriptionListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Subscription`

    Description: List of items



### LocaleFlat

Type: object
Properties:
  code:
    Type: string
    Example: "es-cr"

  name:
    Type: string
    Example: "Spanish (Costa Rica)"



### LocaleFlatResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `LocaleFlat`
    Description: Response payload



### LocaleFlatPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `LocaleFlat`

    Description: List of items



### LocaleFlatListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `LocaleFlat`

    Description: List of items



### PaymentMethod

Type: object
Properties:
  id:
    Type: string
    Description: Credit card id.
    Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"

  cc_mask:
    Type: string
    Description: Masked credit card number.
    Example: "4111-1111-1111-1111"

  cc_brand:
    Type: string
    Description: Type of card.
    Example: "VISA"

  cc_name:
    Type: string
    Description: Name on the credit card.
    Example: "Joe Doe"

  cc_month:
    Type: string
    Description: Expiration month.
    Example: "01"

  cc_year:
    Type: string
    Description: Expiration year.
    Example: "2025"

  default:
    Type: boolean
    Description: Is default payment method.
    Example: true

  address_1:
    Type: string
    Description: Primary billing address line
    Example: "Guachipelín de Escazú"

  address_2:
    Type: string
    Description: Secondary billing address line
    Example: "Ofibodegas #5"

  city:
    Type: string
    Description: City
    Example: "Escazú"

  state:
    Type: string
    Description: State/Province
    Example: "San José"

  zip:
    Type: string
    Description: ZIP/Postal code
    Example: "10203"



### PaymentMethodResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `PaymentMethod`
    Description: Response payload



### PaymentMethodPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `PaymentMethod`

    Description: List of items



### PaymentMethodListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `PaymentMethod`

    Description: List of items



### Locale

Type: object
Properties:
  code:
    Type: string
    Example: "es-cr"

  locale_name:
    Type: string
    Example: "Spanish (Costa Rica)"

  lang_name:
    Type: string
    Example: "Spanish"



### LocaleResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Locale`
    Description: Response payload



### LocalePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Locale`

    Description: List of items



### LocaleListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Locale`

    Description: List of items



### UserProjectAccess

Type: object
Properties:
  id:
    Type: string
    Example: "b40f4aa6-66b4-4751-9665-838e1d9e34a4"

  firstname:
    Type: string
    Example: "Jordane"

  lastname:
    Type: string
    Example: "Walter"

  email:
    Type: string
    Example: "lind.kristofer@hotmail.com"

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`

  last_activity_at:
    Type: integer
    Example: 1764988634

  role:
    Type: App\Data\RoleData
    allOf:
      Reference to: `RoleData`

  invited_by:
    Type: App\Data\UserData
    allOf:
      Reference to: `UserData`

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "es-cr"

    Description: List of locales user can translate to



### UserProjectAccessResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserProjectAccess`
    Description: Response payload



### UserProjectAccessPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserProjectAccess`

    Description: List of items



### UserProjectAccessListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserProjectAccess`

    Description: List of items



### ContentBlockTranslations

Type: object
Properties:
  id:
    Type: string
    Example: "5ea2afeb-2fb1-49bc-a320-9d3bd0abd39f"

  translation_id:
    Type: string
    Example: "16a79e07-9362-4ae5-a5a7-56247b091946"

  content_block_id:
    Type: string
    Example: "bc4f3fd6-4157-49f3-b576-3fbde8bf042f"

  custom_id:
    Type: string
    Example: "6b19daad-731e-4351-9c67-7cac8e8f1ed2"

  category:
    Type: string
    Example: "UI"

  translations:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`


  label:
    Type: string
    Example: "Home"

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634

  untranslated:
    Type: boolean
    Default: false
    Example: true

  translatable:
    Type: boolean
    Default: false
    Example: true

  memory_translated:
    Type: boolean
    Default: false
    Example: true

  human_translated:
    Type: boolean
    Default: false
    Example: true

  ai_translated:
    Type: boolean
    Default: false
    Example: false



### ContentBlockTranslationsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `ContentBlockTranslations`
    Description: Response payload



### ContentBlockTranslationsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ContentBlockTranslations`

    Description: List of items



### ContentBlockTranslationsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `ContentBlockTranslations`

    Description: List of items



### Invoice

Type: object
Properties:
  id:
    Type: string
    Description: Invoice ID.
    Example: "6e917472-1237-4f5a-8cef-00a84125244f"

  file_name:
    Type: string
    Description: File name of the invoice.
    Example: "invoice_123456.pdf"

  type:
    Type: enum
    Enum: ["subscription", "prepaid_credits", "refund", "void"]
    Description: Invoice type.
    Example: "void"

  status:
    Type: string
    Description: Invoice status.
    Example: "paid"

  created_at:
    Type: string
    Description: Creation date of the invoice.
    Example: "2021-01-01 12:00:00"

  updated_at:
    Type: string
    Description: Last update date of the invoice.
    Example: "2021-01-05 14:30:00"



### InvoiceResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Invoice`
    Description: Response payload



### InvoicePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Invoice`

    Description: List of items



### InvoiceListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Invoice`

    Description: List of items



### Project

Type: object
Properties:
  id:
    Type: string
    Example: "ce4ec6cd-1ed5-4764-969d-659c5185948d"

  owner_id:
    Type: string
    Example: "0f9eea73-cfa8-473d-844e-d60a9aaca68c"

  title:
    Type: string
    Example: "Comercado"

  description:
    Type: string
    Example: "Translations for Comercado app"

  base_locale:
    Type: string
    Description: Locale in which project phrase strings are written.
    Example: "en-us"

  organization_id:
    Type: string
    Description: Id of organization the project belongs to
    Example: "6bf25bdd-c2ee-40bf-9dee-a4ff97e70342"

  organization_name:
    Type: string
    Example: "Konopelski, Ullrich and Wolf"

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "fr-ca"

    Description: List of locales the project is meant to be translated to. If the user making the request is a translator, then this list will only include the locales the translator is assigned to.

  default_locales:
    Type: array
    Items: 
      Type: string
      Example: "es-cr"

    Description: Default locale for each of the languages the project is meant to be translated to. If project only has one locale for a certain language, then that will be the default; otherwise one of the locales must be picked as default.

  website_url:
    Type: string
    Example: "https://example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  settings:
    Type: App\Data\ProjectSettingsData
    allOf:
      Reference to: `ProjectSettingsData`

  admin:
    Type: boolean
    Example: true

  last_activity_at:
    Type: integer
    Example: 1764988634

  totals:
    Type: App\Data\TranslationTotals\GeneralProjectTotals
    allOf:
      Reference to: `GeneralProjectTotals`

  role:
    Type: App\Data\RoleData
    allOf:
      Reference to: `RoleData`

  user_joined_at:
    Type: integer
    Description: Timestamp when the user joined the project or when they got access to it
    Example: 1764988634

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634



### ProjectResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Project`
    Description: Response payload



### ProjectPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Project`

    Description: List of items



### ProjectListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Project`

    Description: List of items



### UserSettings

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: The maximum amount that can be drawn from the monthly balance of the user.
    Example: 100

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Whether auto recharge is enabled for the user
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
    Example: 20

  allow_draw_organizations:
    Type: boolean
    Default: true
    Description: The allow draw organizations for the user
    Example: true

  draw_organizations_limit_monthly:
    Type: number
    Format: float
    Description: The draw organizations limit monthly for the user
    Example: 100



### UserSettingsResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserSettings`
    Description: Response payload



### UserSettingsPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserSettings`

    Description: List of items



### UserSettingsListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserSettings`

    Description: List of items



### Organization

Type: object
Properties:
  id:
    Type: string
    Example: "dd11c45f-2962-4400-82b8-6d353aeac909"

  name:
    Type: string
    Example: "Parisian-Hyatt"

  email:
    Type: string
    Example: "parker.judson@rempel.net"

  website_url:
    Type: string
    Example: "https://example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  address:
    Type: App\Data\Address
    allOf:
      Reference to: `Address`

  settings:
    Type: App\Data\OrganizationSettingsData
    allOf:
      Reference to: `OrganizationSettingsData`

  stats:
    Type: App\Data\OrganizationStats
    allOf:
      Reference to: `OrganizationStats`

  admin:
    Type: boolean
    Example: true

  role:
    Type: App\Data\RoleData
    allOf:
      Reference to: `RoleData`

  last_activity_at:
    Type: integer
    Example: 1764988634

  user_joined_at:
    Type: integer
    Description: Timestamp of when the user joined the organization
    Example: 1764988634

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634



### OrganizationResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Organization`
    Description: Response payload



### OrganizationPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Organization`

    Description: List of items



### OrganizationListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Organization`

    Description: List of items



### Trash

Type: object
Properties:
  id:
    Type: integer
    Description: The ID of the trashed item
    Example: 1

  entity_type:
    Type: string
    Description: The type of entity that was trashed
    Example: "Project"

  name:
    Type: string
    Description: The name or identifier of the trashed item
    Example: "My Project"

  context:
    Type: App\Data\TrashItemContext
    allOf:
      Reference to: `TrashItemContext`
    Description: Additional context about the trashed item

  deleted_at:
    Type: integer
    Description: When the item was moved to trash
    Example: 1710925800



### TrashResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Trash`
    Description: Response payload



### TrashPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Trash`

    Description: List of items



### TrashListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Trash`

    Description: List of items



### BalanceSummary

Type: object
Properties:
  prepaid_credits_used:
    Type: number
    Format: float
    Description: Total prepaid credits used in the period
    Example: 1000

  prepaid_credits_available:
    Type: number
    Format: float
    Description: Total prepaid credits currently available
    Example: 2000

  free_credits_used:
    Type: number
    Format: float
    Description: Total free credits used in the period
    Example: 500

  free_credits_available:
    Type: number
    Format: float
    Description: Total free credits currently available
    Example: 1500

  total_credits_used:
    Type: number
    Format: float
    Description: Total credits used in the period
    Example: 1500

  total_credits_available:
    Type: number
    Format: float
    Description: Total credits currently available
    Example: 2000



### BalanceSummaryResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `BalanceSummary`
    Description: Response payload



### BalanceSummaryPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `BalanceSummary`

    Description: List of items



### BalanceSummaryListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `BalanceSummary`

    Description: List of items



### Notification

Type: object
Properties:
  id:
    Type: string
    Example: "79d7e093-fa90-4f71-ae30-219f31abb761"

  message:
    Type: string
    Example: "8 new phrase(s) have been created in Project ABC"

  type:
    Type: enum
    Enum: ["invitation", "new_phrase", "added_to_entity"]
    Description: Type of notification. Should be used by client to decide how to display the notification.
    Example: "added_to_entity"

  data:
    Type: App\Data\NotificationPayloadData
    allOf:
      Reference to: `NotificationPayloadData`
    Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

  created_at:
    Type: integer
    Example: 1764988634

  read_at:
    Type: integer
    Example: 1764988634



### NotificationResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Notification`
    Description: Response payload



### NotificationPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Notification`

    Description: List of items



### NotificationListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Notification`

    Description: List of items



### Plan

Type: object
Properties:
  id:
    Type: string
    Description: Plan ID.
    Example: "a3b8c9d0-1234-5678-9abc-def012345678"

  name:
    Type: string
    Description: Display name of the plan.
    Example: "Business"

  type:
    Type: enum
    Enum: ["free", "business", "enterprise"]
    Description: Type of the plan.
    Example: "enterprise"

  max_organizations:
    Type: integer
    Description: Maximum number of organizations allowed for this plan.
    Example: 3

  max_projects:
    Type: integer
    Description: Maximum number of projects allowed for this plan.
    Example: 10

  max_locales:
    Type: integer
    Description: Maximum number of locales allowed for this plan.
    Example: 5

  max_users:
    Type: integer
    Description: Maximum number of users allowed for this plan.
    Example: 25

  max_translator_users:
    Type: integer
    Description: Maximum number of translator users allowed for this plan.
    Example: 10

  price:
    Type: number
    Format: float
    Description: Monthly price for the plan. Null for Free and Enterprise plans.
    Example: 29



### PlanResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Plan`
    Description: Response payload



### PlanPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Plan`

    Description: List of items



### PlanListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Plan`

    Description: List of items



### Activity

Type: object
Properties:
  date:
    Type: string
    Description: Log date.
    Example: "130"

  get_requests:
    Type: integer
    Description: Total number of get requests.
    Example: 130

  post_requests:
    Type: integer
    Description: Total number of post requests.
    Example: 130

  patch_requests:
    Type: integer
    Description: Total number of patch requests.
    Example: 130

  delete_requests:
    Type: integer
    Description: Total number of delete requests.
    Example: 130



### ActivityResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Activity`
    Description: Response payload



### ActivityPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Activity`

    Description: List of items



### ActivityListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Activity`

    Description: List of items



### TranslationWithPhrase

Type: object
Properties:
  id:
    Type: string
    Example: "9ced23bd-26af-4d80-82fc-533380c2f756"

  translation_id:
    Type: string
    Example: "b9d61f0a-82b2-4ac8-ba9e-5d1971466da7"

  label:
    Type: string
    Description: Sanitized phrase truncated to 25 chars.
    Example: "Home"

  locale:
    Type: string
    Example: "es-es"

  category:
    Type: string
    Description: Phrase context category.
    Example: "UI"

  phrase:
    Type: string
    Example: "Home"

  phrase_id:
    Type: string
    Example: "170e9036-5bc6-4183-aa24-1813c8738d6e"

  content_block_id:
    Type: string
    Example: "49666b64-7eb4-473e-9ab6-2a63b1febe43"

  translation:
    Type: string
    Description: Translation text in the locale provided in this response.
    Example: "Inicio"

  untranslated:
    Type: boolean
    Example: true

  translatable:
    Type: boolean
    Description: Whether phrase is translatable to other languages. For example, brand names are mostly not translatable as they consist of the same text in any language.
    Example: true

  restorable:
    Type: boolean
    Description: Whether this phrase is able to be restored after being marked as untranslatable.
    Example: false

  human_translated:
    Type: boolean
    Description: Whether translation was done by a human.
    Example: true

  memory_translated:
    Type: boolean
    Description: Whether translation comes from translation memory.
    Example: true

  ai_translated:
    Type: boolean
    Description: Whether translation is translated by AI.
    Example: false

  translator:
    Type: App\Http\Resources\UserSimpleResource
    allOf:
      Reference to: `UserSimple`
    Description: User that translated the phrase.

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Description: Machine translator used to translate the phrase.
    Example: "google"

  words:
    Type: integer
    Example: 1

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634



### TranslationWithPhraseResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `TranslationWithPhrase`
    Description: Response payload



### TranslationWithPhrasePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`

    Description: List of items



### TranslationWithPhraseListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationWithPhrase`

    Description: List of items



### UserSimple

Type: object
Properties:
  id:
    Type: string
    Example: "a37651e3-3045-4aaa-b47e-3b88fdd29041"

  firstname:
    Type: string
    Example: "Laisha"

  lastname:
    Type: string
    Example: "Eichmann"

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`
    Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.



### UserSimpleResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserSimple`
    Description: Response payload



### UserSimplePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserSimple`

    Description: List of items



### UserSimpleListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserSimple`

    Description: List of items



### Payment

Type: object
Properties:
  id:
    Type: string
    Description: Payment ID.
    Example: "550e8400-e29b-41d4-a716-446655440000"

  status:
    Type: string
    Description: Payment status.
    Example: "success"

  provider_transaction_id:
    Type: string
    Description: Provider transaction ID.
    Example: "1234567890"

  auth_code:
    Type: string
    Description: Authorization code.
    Example: "AUTH123"

  error:
    Type: string
    Description: Error message if payment failed.
    Example: "Invalid card number"

  created_at:
    Type: string
    Description: Payment created at.
    Example: "2021-01-01 00:00:00"

  cvv_result_code:
    Type: string
    Description: CVV result code.
    Example: "P"

  cavv_result_code:
    Type: string
    Description: CAVV result code.
    Example: "2"



### PaymentResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Payment`
    Description: Response payload



### PaymentPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Payment`

    Description: List of items



### PaymentListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Payment`

    Description: List of items



### Country

Type: object
Properties:
  label:
    Type: string
    Description: Country name
    Example: "Costa Rica"

  code:
    Type: string
    Description: Country code
    Example: "CR"



### CountryResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `Country`
    Description: Response payload



### CountryPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Country`

    Description: List of items



### CountryListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `Country`

    Description: List of items



### PlanCycle

Type: object
Properties:
  id:
    Type: string
    Example: "88acf96c-e37b-40fe-8b49-06995f1005e8"

  name:
    Type: string
    Example: "Stewart Jacobs DVM"

  price:
    Type: number
    Format: float
    Example: 100



### PlanCycleResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `PlanCycle`
    Description: Response payload



### PlanCyclePaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `PlanCycle`

    Description: List of items



### PlanCycleListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `PlanCycle`

    Description: List of items



### UserWithToken

Type: object
Properties:
  id:
    Type: string
    Example: "3c07bcbe-006e-4f69-a998-5ca501a578c1"

  firstname:
    Type: string
    Example: "Margie"

  lastname:
    Type: string
    Example: "Berge"

  email:
    Type: string
    Example: "wilkinson.elinore@cremin.com"

  phone:
    Type: string
    Example: "+1.804.635.8863"

  locale:
    Type: string
    Description: Base locale
    Example: "en-us"

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`

  token:
    Type: string
    Example: "44|lD4YNjoFLRu8l6GlJHAKXwTuAULnzIXknCfh7hs82f9faad4"

  token_type:
    Type: string
    Example: "Bearer"

  expires_at:
    Type: integer
    Example: 1764988634

  email_verified_at:
    Type: integer
    Example: 1764988634



### UserWithTokenResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserWithToken`
    Description: Response payload



### UserWithTokenPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserWithToken`

    Description: List of items



### UserWithTokenListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserWithToken`

    Description: List of items



### UserExtended

Type: object
Properties:
  id:
    Type: string
    Example: "e9670ae4-69d4-43b2-b1cb-7dd4327c4bfc"

  firstname:
    Type: string
    Example: "Estelle"

  lastname:
    Type: string
    Example: "McLaughlin"

  email:
    Type: string
    Example: "schuppe.elmore@gmail.com"

  phone:
    Type: string
    Example: "(630) 622-5121"

  locale:
    Type: string
    Example: "es-cr"

  last_seen_at:
    Type: integer
    Description: Unix timestamp indicating last time the user interacted with the system.
    Example: 1764988634

  created_at:
    Type: integer
    Description: Unix timestamp indicating creation date.
    Example: 1764988634

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`
    Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

  source_locales:
    Type: array
    Items: 
      Type: string
      Example: "en_MH"

    Description: List of locales user can translate from

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "ps_AF"

    Description: List of locales user can translate to

  settings:
    Type: App\Data\UserSettingsData
    allOf:
      Reference to: `UserSettingsData`



### UserExtendedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `UserExtended`
    Description: Response payload



### UserExtendedPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserExtended`

    Description: List of items



### UserExtendedListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `UserExtended`

    Description: List of items



### DeprecatedToken

Type: object
Properties:
  id:
    Type: string
    Example: "9502988d-221c-4431-9773-b42858753cb5"

  token:
    Type: string
    Example: "Home"

  category:
    Type: string
    Example: "UI"

  translatable:
    Type: boolean
    Example: true

  created_at:
    Type: integer
    Example: 1764988634

  updated_at:
    Type: integer
    Example: 1764988634

  deleted_at:
    Type: integer
    Example: 1764988634



### DeprecatedTokenResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `DeprecatedToken`
    Description: Response payload



### DeprecatedTokenPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DeprecatedToken`

    Description: List of items



### DeprecatedTokenListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DeprecatedToken`

    Description: List of items



### DeprecatedContentBlock

Type: object
Properties:
  id:
    Type: string
    Example: "0f1f6b60-7494-42b2-9dd6-6d6a06f9894f"

  custom_id:
    Type: string
    Example: "938c2cc0dcc05f2b68c4287040cfcf71"

  category:
    Type: string
    Example: "UI"

  content:
    Type: string
    Example: "<ul><li>Home</li></ul>"

  label:
    Type: string
    Example: "Home"

  tokens:
    Type: array
    Items: 
      allOf:
        Reference to: `DeprecatedToken`




### DeprecatedContentBlockResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: object
    allOf:
      Reference to: `DeprecatedContentBlock`
    Description: Response payload



### DeprecatedContentBlockPaginatedResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  page:
    Type: integer
    Description: Current page number
    Example: 1

  records_per_page:
    Type: integer
    Description: Number of records per page
    Example: 8

  page_count:
    Type: integer
    Description: Number of pages
    Example: 5

  total_records:
    Type: integer
    Description: Total number of items
    Example: 40

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DeprecatedContentBlock`

    Description: List of items



### DeprecatedContentBlockListResponse

Type: object
Properties:
  status:
    Type: boolean
    Description: Response status
    Example: true

  data:
    Type: array
    Items: 
      allOf:
        Reference to: `DeprecatedContentBlock`

    Description: List of items



### PaymentManagerCreditCardUpdateRequest

Type: object
Properties:
  cc_name:
    Type: string
    Example: ""

  cc_month:
    Type: string
    Example: ""

  cc_year:
    Type: string
    Example: ""

  cc_cvv:
    Type: string
    Example: ""

  address:
    Type: string
    Example: "579 Marcia Passage\nWest Aureliastad, PA 74334"

  address_2:
    Type: string
    Example: "98243"

  city:
    Type: string
    Example: "North Ransom"

  state:
    Type: string
    Example: "Nevada"

  zip:
    Type: string
    Example: "26387-4461"

  validate_with:
    Type: App\Data\PaymentProviderConfig
    allOf:
      Reference to: `PaymentProviderConfig`

  enforce_cvv:
    Type: boolean
    Default: false
    Example: true

  enforce_avs:
    Type: boolean
    Default: false
    Example: true



### InvitationRequest

Type: object
Properties:
  token:
    Type: string
    Example: ""



### TranslatableItemTransferRequest

Type: object
Properties:
  source_project_id (Required):
    Type: string
    Example: "0457bbcd-70f1-4abe-ba09-6a101f50598e"

  target_project_id (Required):
    Type: string
    Example: "09559ed1-8b80-4b05-871a-19986f36956c"

  include_translations:
    Type: boolean
    Default: false
    Description: If true, all translations for the project will be transferred, and all memory translations that are realted to the project phrases will be transferred as well.
    Example: true

  create_target_locales:
    Type: boolean
    Default: false
    Description: Whether to create target locales in target project if they do not exist. If false, only translations to existing locales will be transferred.
    Example: true

  force_transfer:
    Type: boolean
    Default: false
    Description: Force transfer even if projects base locale does not match.
    Example: false

  transfer_mode:
    Type: enum
    Enum: ["copy", "move"]
    Default: "move"
    Description: Transfer mode: copy (default) keeps items in source project, move removes them from source project.
    Example: "move"



### CaptureRequest

Type: object
Properties:
  cc_id (Required):
    Type: string
    Description: The credit card id for the payment
    Example: "d20e71a4-3f0f-4143-aec8-8918e0f8a233"

  client_transaction_id (Required):
    Type: string
    Description: The client transaction id for the payment
    Example: "70a77026-ef66-4169-9839-27a4673c0043"

  amount (Required):
    Type: number
    Format: float
    Description: The amount to pay
    Example: 99

  payment_provider:
    Type: enum
    Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
    Description: The payment provider
    Example: "paypal"



### UpdateSubscriptionRequest

Type: object
Properties:
  user_id (Required):
    Type: string
    Example: "72f1301a-e742-48d3-8672-c50e8bd80bd0"

  plan_type (Required):
    Type: enum
    Enum: ["free", "business", "enterprise"]
    Default: "free"
    Example: "enterprise"

  plan_cycle (Required):
    Type: enum
    Enum: ["monthly", "yearly", "lifetime"]
    Default: "monthly"
    Example: "monthly"

  credit_card:
    Type: App\Data\CreditCardData
    allOf:
      Reference to: `CreditCardData`



### OrganizationInvitationRequest

Type: object
Properties:
  user_id:
    Type: string
    Description: The id of the user to invite. If provided, the email field will be ignored.
    Example: "52fde56d-2e34-4f44-aa9c-c8fcab083660"

  role:
    Type: enum
    Enum: ["organization_admin", "organization_user"]
    Default: "organization_user"
    Description: Role of the user in the organization
    Example: "organization_admin"

  email:
    Type: string
    Description: Provide an email if user is new. Email can also be provided for existing users if userId is not provided in the URI.
    Example: "vabbott@kerluke.com"

  disabled_projects:
    Type: array
    Items: 
      Type: string
      Example: "3f8f583a-1fcf-44ba-894e-23d9693d55ec"

    Description: A list of the ids of projects that will be disabled for the user. This list will sync with the currently disabled projects of the user.



### TranslatableItemRestoreAllRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "80d47c8c-0be5-4e29-9fa8-a87b02d6cb19"

  restore_translations:
    Type: boolean
    Default: false
    Example: true

  restore_memory_translations:
    Type: boolean
    Default: false
    Example: true



### ProjectUpdateRequest

Type: object
Properties:
  organization_id:
    Type: string
    Example: "2cb0b24e-e511-4b11-baa6-808e32240608"

  title:
    Type: string
    Example: "Comercado"

  base_locale:
    Type: string
    Description: Source locale for this project
    Example: "en-us"

  description:
    Type: string
    Example: "Translations for Comercado app"

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "es-cr"

    Description: List of locales user can translate to

  website_url:
    Type: string
    Example: "https://example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  auto_recharge_credit_card_id:
    Type: string
    Example: "3554b087-e7f9-4d08-a3e7-3ad167a09136"

  settings:
    Type: App\Data\ProjectSettingsData
    allOf:
      Reference to: `ProjectSettingsData`



### EmailUpdateRequest

Type: object
Properties:
  email (Required):
    Type: string
    Example: "quinten.jaskolski@hotmail.com"



### BalanceTransactionsRequest

Type: object
Properties:
  type:
    Type: enum
    Enum: ["credit", "auto_recharge", "machine_translation", "draw_from_account", "draw_from_organization", "prepaid_credits_invoiced", "free_credits_granted", "prepaid_credits_transfer", "free_credits_transfer"]
    Description: Transaction type
    Example: "auto_recharge"

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Description: Machine translator code
    Example: "default"

  payment_provider:
    Type: enum
    Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
    Description: Payment provider
    Example: "paypal"

  reference_id:
    Type: string
    Description: Reference ID for the transaction
    Example: "ref_123"

  start_date:
    Type: string
    Description: The start date for filtering transactions
    Example: "2024-07-15"

  end_date:
    Type: string
    Description: The end date for filtering transactions
    Example: "2024-07-31"



### DeletePaymentMethodRequest

Type: object
Properties:
  new_default_payment_method_id:
    Type: string
    Description: ID of the new payment method to set as default (optional)
    Example: "34ba42a0-7e51-4002-88da-8ad83683b1ac"



### TranslatableItemRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "04dd610b-5ceb-40d6-9fcc-e693f14e4edd"

  phrases (Required):
    Type: array
    Items: 
      allOf:
        Reference to: `PhraseRequest`


  type:
    Type: enum
    Enum: ["phrase", "content_block"]
    Default: "phrase"
    Example: "content_block"

  custom_id:
    Type: string
    Description: Custom id generated by the client to represent and manipulate the content block. Only required if type is content_block.
    Example: "764f14dc-9db1-42e2-83c8-9f9d7310557b"

  content:
    Type: string
    Description: The html content of the content block. Only required if type is content_block.
    Example: "<ul><li>Home</li><li>About</li></ul>"

  category:
    Type: string
    Description: The category of the content block. Only required if type is content_block.
    Example: "UI"

  label:
    Type: string
    Description: Label to identify the content block. If left empty then the first phrase with at least 5 chars will be chosen as the label. Only required if type is content_block.
    Example: "Main Menu"



### TranslatableItemRestoreRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "5539b4f0-41a1-4018-ac99-918f3439beca"

  restoreable_ids (Required):
    Type: array
    Items: 
      Type: string
      Example: "92a7a6ce-545a-42a4-94f8-b7194bf28d3d"


  restore_translations:
    Type: boolean
    Default: false
    Example: true

  restore_memory_translations:
    Type: boolean
    Default: false
    Example: true



### SetDefaultPaymentMethodRequest

Type: object
Properties:
  payment_method_id (Required):
    Type: string
    Description: The ID of the payment method to set as default.
    Example: "19b3b92a-3399-4a3f-a121-afbd89a75d22"



### UserRequest

Type: object
Properties:
  firstname:
    Type: string
    Example: "Deion"

  lastname:
    Type: string
    Example: "Crona"

  phone:
    Type: string
    Example: "1-223-964-6120"

  locale:
    Type: string
    Example: "sr_CS"

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`
    Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found.

  source_locales:
    Type: array
    Items: 
      Type: string
      Example: "kn_IN"

    Description: List of locales user can translate from

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "hi_IN"

    Description: List of locales user can translate to

  auto_recharge_credit_card_id:
    Type: string
    Example: "f2422b72-930f-41f7-a60b-7fcc8a42a388"



### MachineTranslationTransactionsRequest

Type: object
Properties:
  user_id:
    Type: string
    Example: "b7e45ca2-5e1a-4d25-b41f-f9cdd5d74f6c"

  locale:
    Type: string
    Example: "es-cr"

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Description: Machine translator code
    Example: "chatgpt4o"

  transaction_type:
    Type: enum
    Enum: ["translation", "detection"]
    Description: Machine translator transaction type
    Example: "translation"

  start_date:
    Type: string
    Description: The start date for filtering transactions
    Example: "2024-07-15"

  end_date:
    Type: string
    Description: The end date for filtering transactions
    Example: "2024-07-31"



### UserSettingsRequest

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: The maximum amount that can be drawn from the monthly balance of the user.
    Example: 100

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Whether auto recharge is enabled for the user
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
    Example: 20

  allow_draw_organizations:
    Type: boolean
    Default: true
    Description: The allow draw organizations for the user
    Example: true

  draw_organizations_limit_monthly:
    Type: number
    Format: float
    Description: The draw organizations limit monthly for the user
    Example: 100



### TranslationDeleteRequest

Type: object
Properties:
  locale (Required):
    Type: string
    Description: Translation locale.
    Example: "es-es"

  updateTM:
    Type: boolean
    Default: false
    Description: Whether to delete related entries from translation memory. This field is optional and false if not present.
    Example: true

  delete_language_locales:
    Type: boolean
    Default: false
    Description: If true, all translations for locales of the same language as the locale provided will be deleted as well.
    Example: true



### PhraseRequest

Type: object
Properties:
  phrase (Required):
    Type: string
    Example: "Home"

  category:
    Type: string
    Description: The category of the phrase. This field is ignored for content block creation, the category send in the root of the request will be used instead.
    Example: "UI"

  translatable:
    Type: boolean
    Description: Whether to mark the phrase as translatable or non-translatable for all locales. If not provided and the phrase already exists, the existing value will be used. If not provided and the phrase does not exist, the phrase will be marked as translatable by default.
    Example: true



### ProjectUserRequest

Type: object
Properties:
  role:
    Type: enum
    Enum: ["project_admin", "project_user", "translator"]
    Example: "translator"

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "sh_YU"

    Description: List of locales translator can translate to. Only send this if role is of type translator.



### PaymentManagerPaymentRequest

Type: object
Properties:
  cc_id (Required):
    Type: string
    Example: "a139dda2-e945-4214-aaef-8a0db0acfc66"

  client_transaction_id (Required):
    Type: string
    Example: "610f225c-6754-4c88-913d-bd00e2095bda"

  amount (Required):
    Type: number
    Format: float
    Example: 100

  payment_provider_config:
    Type: App\Data\PaymentProviderConfig
    allOf:
      Reference to: `PaymentProviderConfig`

  type:
    Type: enum
    Enum: ["capture", "authorize"]
    Default: "capture"
    Example: "capture"

  description:
    Type: string
    Example: ""

  descriptor:
    Type: string
    Example: ""

  descriptor_phone:
    Type: string
    Example: "+1.225.257.9161"

  descriptor_url:
    Type: string
    Example: "http://www.rohan.com/"

  duplicate_transaction_window:
    Type: integer
    Example: 0

  enforce_cvv:
    Type: boolean
    Default: false
    Example: true

  enforce_avs:
    Type: boolean
    Default: false
    Example: true



### TranslationCreateRequest

Type: object
Properties:
  locale (Required):
    Type: string
    Description: Translation locale.
    Example: "es-es"

  translation:
    Type: string
    Description: Translation in queried locale. Only required if translatable is true and request is for a single phrase; otherwise this field will be ignored.
    Example: "Inicio"

  updateTM:
    Type: boolean
    Default: false
    Description: Whether to save this translation in translation memory.
    Example: true

  translatable:
    Type: boolean
    Default: true
    Description: If this flag is false, the translatable item will be marked as untranslatable for requested locale and all project target locales of the same language the user has access to.
    Example: true

  translations:
    Type: array
    Items: 
      allOf:
        Reference to: `TranslationData`

    Description: List of translations for content block. Should be used when translatable item is a content block, unless marking all phrases inside the content block as untranslatable. In that case the translatable field sibling to this one must be set to false and this field can be left empty.



### GetMachineTranslationRequest

Type: object
Properties:
  locale (Required):
    Type: string
    Description: Target locale for the machine translation
    Example: "es-cr"

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Custom machine translator to use. If not provided, machine translator in project settings will be used.
    Example: "xai"



### DateRangeRequest

Type: object
Properties:
  start_date:
    Type: string
    Description: Start date to filter by
    Example: "2024-01-01"

  end_date:
    Type: string
    Description: End date to filter by
    Example: "2024-01-31"



### RestoreRequest

Type: object
Properties:
  restoreable_ids (Required):
    Type: array
    Items: 
      Type: string
      Example: "977a3009-a7a6-4876-a893-acfa4bd92fc3"




### RecoverSubscriptionRequest

Type: object
Properties:
  payment_method_id:
    Type: string
    Description: The ID of the payment method to use for recovery
    Example: "d410a78f-5558-4ba1-9af2-3008052e8a92"

  card_info:
    Type: App\Http\Requests\CreditCardRequest
    allOf:
      Reference to: `CreditCardRequest`
    Description: Credit card information for recovery (optional)

  payment_provider:
    Type: enum
    Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
    Description: The payment provider to use for recovery
    Example: "authorize_net"



### CreditCardRequest

Type: object
Properties:
  cc_number (Required):
    Type: string
    Description: Full credit card number.
    Example: "4111111111111111"

  cc_month (Required):
    Type: string
    Description: Card expiration month (2 digits).
    Example: "02"

  cc_year (Required):
    Type: string
    Description: Card expiration year (4 digits).
    Example: "2025"

  cc_name:
    Type: string
    Description: Cardholder name as it appears on the card.
    Example: "John Doe"

  cc_cvv:
    Type: string
    Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
    Example: "123"

  country_code:
    Type: string
    Description: Two-letter ISO country code where the card was issued or the billing address is located.
    Example: "US"

  billing_address:
    Type: App\Data\BillingAddressData
    allOf:
      Reference to: `BillingAddressData`
    Description: Billing address information

  is_default:
    Type: boolean
    Default: false
    Description: Set this card as the default payment method.
    Example: true



### TranslationRestoreAllRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "7cd76a80-8c54-4de2-96c5-a258632140c1"

  restore_memory_translations:
    Type: boolean
    Default: false
    Example: true



### SingleProjectIdRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "f2e25078-4c4b-4f49-b474-060ecd760089"



### PaymentManagerVoidRequest

Type: object
Properties:
  provider_transaction_id (Required):
    Type: string
    Example: "a2372b3c-2049-4cbf-902f-dd91b8a6e599"

  payment_provider_config:
    Type: App\Data\PaymentProviderConfig
    allOf:
      Reference to: `PaymentProviderConfig`

  description:
    Type: string
    Example: ""



### SetRechargeCreditCardRequest

Type: object
Properties:
  cc_id (Required):
    Type: string
    Description: The credit card id for the payment
    Example: "f3115745-511e-460b-9813-1094a5099bbb"



### PaymentManagerRefundRequest

Type: object
Properties:
  provider_transaction_id (Required):
    Type: string
    Example: "053d8842-c86b-47a6-943a-dca96a3ad468"

  amount (Required):
    Type: number
    Format: float
    Example: 100

  payment_provider_config (Required):
    Type: App\Data\PaymentProviderConfig
    allOf:
      Reference to: `PaymentProviderConfig`

  description:
    Type: string
    Example: ""



### RegisterRequest

Type: object
Properties:
  firstname (Required):
    Type: string
    Example: "Leonora"

  lastname (Required):
    Type: string
    Example: "Swaniawski"

  email (Required):
    Type: string
    Example: "bryce.simonis@hotmail.com"

  password (Required):
    Type: string
    Example: "K4x5mnscP&555"

  password_confirmation (Required):
    Type: string
    Example: "K4x5mnscP&555"

  organization:
    Type: App\Data\OrganizationData
    allOf:
      Reference to: `OrganizationData`

  plan_type (Required):
    Type: enum
    Enum: ["free", "business", "enterprise"]
    Default: "free"
    Example: "enterprise"

  plan_cycle (Required):
    Type: enum
    Enum: ["monthly", "yearly", "lifetime"]
    Default: "monthly"
    Example: "lifetime"

  credit_card:
    Type: App\Data\CreditCardData
    allOf:
      Reference to: `CreditCardData`

  billing_address:
    Type: App\Data\BillingAddressData
    allOf:
      Reference to: `BillingAddressData`

  use_billing_address_for_payment (Required):
    Type: boolean
    Default: false
    Description: If true and credit card is provided, the billing address will be used as the payment method address
    Example: true



### OptionalLocaleRequest

Type: object
Properties:
  locale:
    Type: string
    Description: Translation locale.
    Example: "es-es"



### OrganizationUpdateRequest

Type: object
Properties:
  name:
    Type: string
    Example: "Kutch, Fritsch and Becker"

  email:
    Type: string
    Example: "gcorwin@yahoo.com"

  website_url:
    Type: string
    Example: "https://www.example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  address:
    Type: App\Data\Address
    allOf:
      Reference to: `Address`

  auto_recharge_credit_card_id:
    Type: string
    Example: "4ece2d6d-ff66-437b-b422-c4761227b4f8"

  settings:
    Type: App\Data\OrganizationSettingsData
    allOf:
      Reference to: `OrganizationSettingsData`



### ProjectInvitationRequest

Type: object
Properties:
  user_id:
    Type: string
    Description: The id of the user to invite. If this field is provided, then the email field will be ignored.
    Example: "3b1f18d7-81ff-4b70-ae34-af7bc6a28ba8"

  email:
    Type: string
    Description: The email of the user to invite. Should be provided if the user is not part of the Langsys platform yet. If the email provided is for an existing user then this will behave in the same way as sending that user_id in the request
    Example: "heath.flatley@hotmail.com"

  role:
    Type: enum
    Enum: ["project_admin", "project_user", "translator"]
    Default: "project_user"
    Example: "project_user"

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "ee_GH"

    Description: List of locales translator can translate to. Only send this if role is of type translator.



### TransferCreditRequest

Type: object
Properties:
  prepaid_credits:
    Type: number
    Format: float
    Description: Amount of prepaid credits to transfer.
    Example: 100

  free_credits:
    Type: number
    Format: float
    Description: Amount of free credits to transfer.
    Example: 100



### ApiKeyCreateRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Description: The project ID to associate the API key with.
    Example: "9b91ddef-3fa5-4125-988b-cc76ba4c78cc"

  name (Required):
    Type: string
    Description: The name of the API key.
    Example: "My API Key"

  description:
    Type: string
    Example: "This API key is used for production environment"

  type (Required):
    Type: enum
    Enum: ["read", "write"]
    Default: "read"
    Description: The type of the API key.
    Example: "read"

  active (Required):
    Type: boolean
    Default: true
    Description: Whether the API key is active.
    Example: true



### NotificationMarkStatusRequest

Type: object
Properties:
  notification_ids:
    Type: array
    Items: 
      Type: string
      Example: "18c2c202-556d-4a09-a3d7-e26750fe137d"

    Description: Array of notification IDs to mark as read or unread



### LoginRequest

Type: object
Properties:
  email (Required):
    Type: string
    Description: Login email
    Example: "sadie.grimes@gmail.com"

  password (Required):
    Type: string
    Description: Login password
    Example: "nR6#Aq^#T<?kI."

  device_id (Required):
    Type: string
    Description: This represents the client application, and the same value will need to be sent as a an x-device-id header in all subsequent requests.
    Example: "postman-24ba95bf"

  remember_me:
    Type: boolean
    Default: false
    Description: If set to true token will be valid for one week
    Example: true



### ProjectRequest

Type: object
Properties:
  organization_id (Required):
    Type: string
    Example: "7bcd0875-d251-4eec-9fe8-89f07e95d3ca"

  title (Required):
    Type: string
    Example: "Comercado"

  base_locale (Required):
    Type: string
    Example: "en-us"

  description:
    Type: string
    Example: "Translations for Comercado app"

  target_locales:
    Type: array
    Items: 
      Type: string
      Example: "es-cr"

    Description: List of locales user can translate to

  website_url:
    Type: string
    Example: "https://example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`
    Description: Favicon of the project. If not provided, Langsys will attempt to fetch it from the website URL

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`
    Description: Logo of the project. If not provided, Langsys will attempt to fetch it from the website URL

  auto_recharge_credit_card_id:
    Type: string
    Example: "87e3ae2c-7329-415f-83cc-9c6e10b101e9"

  settings:
    Type: App\Data\ProjectSettingsData
    allOf:
      Reference to: `ProjectSettingsData`



### UserProjectSettingsRequest

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



### PaymentManagerCreditCardRequest

Type: object
Properties:
  user_id (Required):
    Type: string
    Example: "1c9778e3-ed32-4775-bee6-9ca5902b1aca"

  cc_number (Required):
    Type: string
    Example: ""

  cc_month (Required):
    Type: string
    Example: ""

  cc_year (Required):
    Type: string
    Example: ""

  cc_name:
    Type: string
    Example: ""

  cc_cvv:
    Type: string
    Example: ""

  country_code:
    Type: string
    Example: "KG"

  address:
    Type: string
    Example: "1670 Romaguera Ridges Apt. 533\nGusikowskiville, WY 01187-4549"

  address_2:
    Type: string
    Example: "942"

  city:
    Type: string
    Example: "Schmelerbury"

  state:
    Type: string
    Example: "Washington"

  zip:
    Type: string
    Example: "28049"

  validate_with:
    Type: App\Data\PaymentProviderConfig
    allOf:
      Reference to: `PaymentProviderConfig`

  enforce_cvv:
    Type: boolean
    Default: false
    Example: true

  enforce_avs:
    Type: boolean
    Default: false
    Example: true



### OrganizationRequest

Type: object
Properties:
  name (Required):
    Type: string
    Example: "Miller Group"

  email (Required):
    Type: string
    Example: "tyrell46@gmail.com"

  website_url:
    Type: string
    Example: "https://www.example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  settings:
    Type: App\Data\OrganizationSettingsData
    allOf:
      Reference to: `OrganizationSettingsData`

  auto_recharge_credit_card_id:
    Type: string
    Example: "71bce8fc-3c4b-4a4e-9bbd-c2d4dd30c9f8"

  address:
    Type: App\Data\Address
    allOf:
      Reference to: `Address`



### AddCreditRequest

Type: object
Properties:
  amount (Required):
    Type: number
    Format: float
    Description: Amount of credit to add.
    Example: 100



### TranslatableItemListRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "524dfdcc-5814-4721-8998-3bb3e1e88901"

  locale (Required):
    Type: string
    Description: Translation locale.
    Example: "es-es"

  format:
    Type: enum
    Enum: ["flat", "data"]
    Description: Translation format.
    Example: "flat"



### ApiKeyUpdateRequest

Type: object
Properties:
  name:
    Type: string
    Description: The name of the API key.
    Example: "My API Key"

  description:
    Type: string
    Example: "This API key is used for production environment"

  type:
    Type: enum
    Enum: ["read", "write"]
    Description: The type of the API key.
    Example: "write"

  active:
    Type: boolean
    Description: Whether the API key is active.
    Example: true



### TranslatableItemDeleteRequest

Type: object
Properties:
  translatable_item_ids:
    Type: array
    Items: 
      Type: string
      Example: "56cb019d-a2b6-47b3-ba07-2bc9e9685a64"

    Description: Array of translatable item IDs to delete

  delete_translations:
    Type: boolean
    Default: false
    Description: Whether to also delete translations
    Example: true

  delete_memory_translations:
    Type: boolean
    Default: false
    Description: Whether to also delete memory translations
    Example: true



### DeleteProjectRequest

Type: object
Properties:
  target_project_id:
    Type: string
    Description: Project ID to transfer phrases to before deletion. If not provided, phrases will be deleted.
    Example: "b5eeb20d-25b7-4f8e-bc93-53d054a516b6"

  include_translations:
    Type: boolean
    Default: false
    Description: Whether to also transfer translations.
    Example: true

  create_target_locales:
    Type: boolean
    Default: false
    Description: Whether to create target locales in target project if they do not exist. If false, only translations to existing locales will be transferred.
    Example: true

  force_transfer:
    Type: boolean
    Default: false
    Description: Force transfer even if projects base locale does not match.
    Example: true



### SingleLocaleRequest

Type: object
Properties:
  locale (Required):
    Type: string
    Description: Translation locale.
    Example: "es-es"



### LocalesIndexRequest

Type: object
Properties:
  locales:
    Type: array
    Items: 
      Type: string
      Example: "so_KE"


  project_id:
    Type: string
    Example: "9915716d-9caf-4603-b776-e35d7f045079"

  append_target_locales (Required):
    Type: boolean
    Default: true
    Description: Whether to append target locales to the response.
    Example: true



### UserOrganizationSettingsRequest

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



### PasswordUpdateRequest

Type: object
Properties:
  password (Required):
    Type: string
    Example: "';4?5zo>bguL0%$=B'7"

  new_password (Required):
    Type: string
    Example: "BdlyiN21$!glld!"

  new_password_confirmation (Required):
    Type: string
    Example: "BdlyiN21$!glld!"



### CreditCardUpdateRequest

Type: object
Properties:
  cc_name:
    Type: string
    Description: Cardholder name as it appears on the card.
    Example: "John Doe"

  cc_month:
    Type: string
    Description: Card expiration month (2 digits).
    Example: "02"

  cc_year:
    Type: string
    Description: Card expiration year (4 digits).
    Example: "2025"

  cc_cvv:
    Type: string
    Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
    Example: "123"

  billing_address:
    Type: App\Data\BillingAddressData
    allOf:
      Reference to: `BillingAddressData`
    Description: Billing address information



### MachineTranslateUntranslatedRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "822091b3-66b0-42d2-8e17-8f59091de522"

  locale (Required):
    Type: string
    Description: Target locale for machine translation of untranslated phrases
    Example: "es-cr"

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Custom machine translator to use. If not provided, machine translator in project settings will be used.
    Example: "google"



### DeleteAllTranslatableItemsRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Description: Project ID to delete all translatable items from
    Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

  delete_translations:
    Type: boolean
    Default: false
    Description: Whether to also delete translations
    Example: true

  delete_memory_translations:
    Type: boolean
    Default: false
    Description: Whether to also delete memory translations
    Example: true



### MachineTranslateTranslatableItemsRequest

Type: object
Properties:
  translatable_item_ids (Required):
    Type: array
    Items: 
      Type: string
      Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

    Description: List of translatable item IDs to machine translate. For content blocks, all associated phrases will be translated. All translatable items must belong to the same project.

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Custom machine translator to use. If not provided, machine translator in project settings will be used.
    Example: "default"

  locales:
    Type: array
    Items: 
      Type: string
      Example: "es-es"

    Description: List of target locales to translate to. If not provided, all project target locales will be used.



### TranslationRestoreRequest

Type: object
Properties:
  project_id (Required):
    Type: string
    Example: "e936c54e-676e-4446-81e3-11face56e37a"

  restoreable_ids (Required):
    Type: array
    Items: 
      Type: string
      Example: "78630ab3-86cf-48fb-ac4c-09f6ab337843"




### UserInvitationRequest

Type: object
Properties:
  user_id:
    Type: string
    Description: The id of the user to invite. If this field is provided, then the email field will be ignored.
    Example: "44b341b1-1946-45ff-a48c-eb33065cb8c3"

  email:
    Type: string
    Description: This field should be provided if the userId is not provided. It should be used for users who are not Langsys users. If the email provided is for an existing user then this will behave in the same way as sending the userId.
    Example: "mbatz@tromp.com"



### OrganizationUserRequest

Type: object
Properties:
  role:
    Type: enum
    Enum: ["organization_admin", "organization_user"]
    Default: "organization_user"
    Description: Role of the user in the organization
    Example: "organization_user"

  disabled_projects:
    Type: array
    Items: 
      Type: string
      Example: "3f8f583a-1fcf-44ba-894e-23d9693d55ec"

    Description: A list of the ids of projects that will be disabled for the user. This list will sync with the currently disabled projects of the user.



### DeprecatedContentBlockRequest

Type: object
Properties:
  custom_id (Required):
    Type: string
    Description: Custom id generated by the client to represent and manipulate the content block. Flat data format output will use this id to index phrases inside content blocks.
    Example: "938c2cc0dcc05f2b68c4287040cfcf71"

  category (Required):
    Type: string
    Description: The category chosen will be written to every phrase in this content block.
    Example: "UI"

  content (Required):
    Type: string
    Description: HTML markup to display the content block to translators.
    Example: "<ul><li>Home</li><li>About</li></ul>"

  tokens (Required):
    Type: array
    Items: 
      Type: string
      Example: "Home"

    Description: List of phrases to be added.

  label:
    Type: string
    Description: Label to identify the content block. If left empty then the first phrase with at least 5 chars will be chosen as the label.
    Example: "Main Menu"



### DeprecatedTokenListRequest

Type: object
Properties:
  tokens (Required):
    Type: array
    Items: 
      allOf:
        Reference to: `DeprecatedTokenRequest`




### DeprecatedTokenRequest

Type: object
Properties:
  token (Required):
    Type: string
    Example: "Home"

  category:
    Type: string
    Example: "UI"



### DeprecatedTranslationListRequest

Type: object
Properties:
  locale (Required):
    Type: string
    Description: Translation locale.
    Example: "es-es"

  format:
    Type: enum
    Enum: ["flat", "data"]
    Description: Translation format.
    Example: "data"



### OrganizationBasic

Type: object
Properties:
  id:
    Type: string
    Example: "ddd131a2-2776-4642-8cfc-3c35a2ed1469"

  name:
    Type: string
    Description: The name of the organization
    Example: "Langsys Organization"



### UserProjectSettingsData

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



### PaymentProviderConfig

Type: object
Properties:
  provider:
    Type: string
    Description: The payment provider
    Example: "authorize_net"

  username:
    Type: string
    Description: The username for the payment provider
    Example: "joedoe"

  key:
    Type: string
    Description: The key for the payment provider
    Example: "gqjzC66tvYa4Xh7mGC9Cb6687B9Tg85h"



### ImageKitDocumentData

Type: object
Properties:
  name:
    Type: string
    Description: The name of the file
    Example: "Rigoberto Hahn"

  url:
    Type: string
    Description: The URL to access the file
    Example: "http://www.stokes.biz/consequatur-nihil-aspernatur-velit-dolor-et.html"



### UserData

Type: object
Properties:
  id:
    Type: string
    Example: "dd3fab24-3954-407f-ac04-7e590ca5f632"

  firstname:
    Type: string
    Example: "Modesto"

  lastname:
    Type: string
    Example: "Green"

  email:
    Type: string
    Example: "schaden.laron@gmail.com"

  phone:
    Type: string
    Example: "(401) 259-3149"

  locale:
    Type: string
    Example: "kk_KZ"

  last_seen_at:
    Type: integer
    Example: 1764988634

  avatar:
    Type: App\Data\Avatar
    allOf:
      Reference to: `Avatar`
    Description: Avatar object with meta data and urls for the different sizes. Defaults to gravatar urls if not found



### RoleData

Type: object
Properties:
  value:
    Type: string
    Description: Role value
    Example: "organization_admin"

  label:
    Type: string
    Description: Role label
    Example: "Organization Admin"



### UserOrganizationSettingsData

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.



### BillingAddressData

Type: object
Properties:
  address_1:
    Type: string
    Description: Primary billing address line
    Example: "Guachipelín de Escazú"

  address_2:
    Type: string
    Description: Secondary billing address line
    Example: "Ofibodegas #5"

  city:
    Type: string
    Description: City
    Example: "Escazú"

  state:
    Type: string
    Description: State/Province
    Example: "San José"

  zip:
    Type: string
    Description: ZIP/Postal code
    Example: "10203"



### CreditCardData

Type: object
Properties:
  cc_number:
    Type: string
    Description: Full credit card number.
    Example: "4111111111111111"

  cc_month:
    Type: string
    Description: Card expiration month (2 digits).
    Example: "02"

  cc_year:
    Type: string
    Description: Card expiration year (4 digits).
    Example: "2025"

  cc_name:
    Type: string
    Description: Cardholder name as it appears on the card.
    Example: "John Doe"

  cc_cvv:
    Type: string
    Description: Card Verification Value - the 3-digit security code on the back of most cards (4 digits on front for American Express).
    Example: "123"

  country_code:
    Type: string
    Description: Two-letter ISO country code where the card was issued or the billing address is located.
    Example: "US"

  address_1:
    Type: string
    Description: Address for the card.
    Example: "123 Main St"

  address_2:
    Type: string
    Description: Additional address line for the card.
    Example: "Apt 4B"

  city:
    Type: string
    Description: City for the card.
    Example: "San Francisco"

  state:
    Type: string
    Description: State/province for the card.
    Example: "CA"

  zip:
    Type: string
    Description: ZIP/postal code for the card.
    Example: "94105"



### ProjectBasic

Type: object
Properties:
  id:
    Type: string
    Example: "7e9bd2ba-9e96-4189-bfb1-dccbde3c96be"

  title:
    Type: string
    Example: "Comercado"



### Address

Type: object
Properties:
  address_1:
    Type: string
    Example: "Guachipelín de Escazú"

  address_2:
    Type: string
    Example: "Ofibodegas #5"

  city:
    Type: string
    Example: "Escazú"

  state:
    Type: string
    Example: "San José"

  zip:
    Type: string
    Example: "10203"

  country_code:
    Type: string
    Example: "CR"

  country:
    Type: string
    Example: "Costa Rica"



### PaymentMethodData

Type: object
Properties:
  payment_data:
    Type: array
    Items: 
      Type: string
      Example: ""

    Description: Payment data array received from the payment manager

  payment_provider:
    Type: enum
    Enum: ["authorize_net", "stripe", "paypal", "credomatic", "other"]
    Description: Payment provider.
    Example: "authorize_net"

  is_default:
    Type: boolean
    Default: false
    Description: Indicates if the payment method should be set as default
    Example: true

  payment_method_id:
    Type: string
    Description: Payment method ID.
    Example: "1234567890"

  country_code:
    Type: string
    Description: Country code of the credit card.
    Example: "cr"



### TrashItemData

Type: object
Properties:
  entity_type:
    Type: enum
    Enum: ["Project", "Phrase", "Organization", "Translation", "ContentBlock"]
    Description: Type of entity
    Example: "Organization"

  id:
    Type: string
    Description: ID of the entity
    Example: "13f7a188-d40f-4439-9c1b-c757c8cb158b"



### ProjectSettingsData

Type: object
Properties:
  use_translation_memory:
    Type: boolean
    Default: true
    Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
    Example: true

  machine_translate_new_phrases:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
    Example: true

  use_machine_translations:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
    Example: true

  translate_base_locale_only:
    Type: boolean
    Default: false
    Description: Project wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
    Example: true

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Project wide setting that determines which machine translator to use.
    Example: "default"

  broadcast_translations:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
    Example: true

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: Project wide setting that determines the monthly usage limit for the project.
    Example: 20

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Project wide setting that determines whether the system should automatically recharge the project when the usage limit is reached.
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: Project wide setting that determines the threshold for automatic recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: Project wide setting that determines the amount to recharge.
    Example: 20

  auto_recharge_source:
    Type: enum
    Enum: ["organization_balance", "credit_card", "organization_balance_or_credit_card", "credit_card_or_organization_balance"]
    Default: "organization_balance_or_credit_card"
    Description: Project wide setting that determines the source of the automatic recharge.
    Example: "organization_balance_or_credit_card"



### OrganizationStats

Type: object
Properties:
  projects:
    Type: integer
    Description: Total number of projects in the organization.
    Example: 15

  users:
    Type: integer
    Description: Total number of users in the organization.
    Example: 25



### Photo

Type: object
Properties:
  id:
    Type: string
    Example: "eafe28eb-0886-4c82-92bc-9a4bb5a6b359"

  path:
    Type: string
    Description: Local path of the photo.
    Example: "/public/images"

  provider:
    Type: enum
    Enum: ["gravatar", "imagekit", "custom"]
    Example: "imagekit"

  width:
    Type: integer
    Description: Width of the photo in pixels.
    Example: 445

  height:
    Type: integer
    Description: Height of the photo in pixels.
    Example: 214

  original:
    Type: string
    Description: Url of the original size of the photo
    Example: "https://example.com/original.jpg"

  medium:
    Type: string
    Description: Url of the medium size of the photo
    Example: "https://example.com/medium.jpg"

  thumb:
    Type: string
    Description: Url of the thumbnail size of the photo
    Example: "https://example.com/thumb.jpg"



### TrashItemContext

Type: object
Properties:
  organization:
    Type: App\Data\OrganizationContext
    allOf:
      Reference to: `OrganizationContext`
    Description: Organization information

  project:
    Type: App\Data\ProjectContext
    allOf:
      Reference to: `ProjectContext`
    Description: Project information



### Avatar

Type: object
Properties:
  width:
    Type: integer
    Example: 481

  height:
    Type: integer
    Example: 396

  original_url:
    Type: string
    Example: "http://www.jacobs.com/eum-libero-debitis-eaque-incidunt-sint-omnis"

  thumb_url:
    Type: string
    Example: "http://www.schroeder.biz/unde-doloribus-quidem-consectetur-placeat.html"

  medium_url:
    Type: string
    Example: "http://www.kulas.com/magnam-aperiam-facilis-natus-eligendi-maxime-odio"

  id:
    Type: string
    Example: "1e7b475e-1319-4793-a944-45b45a5abc28"

  path:
    Type: string
    Description: Path of local file
    Example: "/public/images"



### GeneralProjectTotals

Type: object
Properties:
  phrases:
    Type: integer
    Description: Total number of phrases in project.
    Example: 291

  words:
    Type: integer
    Description: Total number of words in project.
    Example: 755

  words_to_translate:
    Type: integer
    Description: Total number of words to translate in project. This is equivalent to words * target_locales.
    Example: 3020

  target_locales:
    Type: integer
    Description: Total number of target locales the user can access. Translators can only see target locales assigned to them.
    Example: 4



### LocaleTotals

Type: object
Properties:
  total:
    Type: integer
    Example: 27

  words:
    Type: integer
    Example: 72

  locale:
    Type: string
    Example: "es-cr"



### TotalsWithLocales

Type: object
Properties:
  total:
    Type: integer
    Example: 311

  words:
    Type: integer
    Example: 801

  locales:
    Type: array
    Items: 
      allOf:
        Reference to: `LocaleTotals`




### WordTranslationTotals

Type: object
Properties:
  total:
    Type: integer
    Description: Total number of words translated in project.
    Example: 803

  human:
    Type: integer
    Description: Total number of human translations in project.
    Example: 778

  ai:
    Type: integer
    Description: Total number of ai translations in project.
    Example: 25



### LocaleTranslationTotals

Type: object
Properties:
  total:
    Type: integer
    Description: Total number of translations in locale.
    Example: 270

  human:
    Type: integer
    Description: Total number of human translations in locale.
    Example: 250

  ai:
    Type: integer
    Description: Total number of ai translations in locale.
    Example: 20

  locale:
    Type: string
    Description: Locale code.
    Example: "es-cr"

  words:
    Type: App\Data\TranslationTotals\WordTranslationTotals
    allOf:
      Reference to: `WordTranslationTotals`



### GlobalTranslationTotals

Type: object
Properties:
  total:
    Type: integer
    Description: Total number of translations in project.
    Example: 712

  human:
    Type: integer
    Description: Total number of human translations in project.
    Example: 618

  ai:
    Type: integer
    Description: Total number of ai translations in project.
    Example: 94

  words:
    Type: App\Data\TranslationTotals\WordTranslationTotals
    allOf:
      Reference to: `WordTranslationTotals`

  locales:
    Type: array
    Items: 
      allOf:
        Reference to: `LocaleTranslationTotals`




### OrganizationContext

Type: object
Properties:
  id:
    Type: string
    Description: The ID of the organization
    Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

  name:
    Type: string
    Description: The name of the organization
    Example: "My Organization"



### OrganizationData

Type: object
Properties:
  name:
    Type: string
    Example: "My Organization"

  email:
    Type: string
    Example: "ffritsch@koepp.com"

  website_url:
    Type: string
    Example: "https://www.example.com"

  icon:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  logo:
    Type: App\Data\Photo
    allOf:
      Reference to: `Photo`

  settings:
    Type: App\Data\OrganizationSettingsData
    allOf:
      Reference to: `OrganizationSettingsData`

  address:
    Type: App\Data\Address
    allOf:
      Reference to: `Address`



### ProjectContext

Type: object
Properties:
  id:
    Type: string
    Description: The ID of the project
    Example: "10a14bd4-4e17-4524-ab06-5b3ac55f7cf9"

  name:
    Type: string
    Description: The name of the project
    Example: "My Project"



### NotificationData

Type: object
Properties:
  id:
    Type: string
    Example: "a2840b56-dad2-40ec-b391-608f4053d072"

  message:
    Type: string
    Example: "8 new phrase(s) have been created in Project ABC"

  type:
    Type: enum
    Enum: ["invitation", "new_phrase", "added_to_entity"]
    Description: Type of notification. Should be used by client to decide how to display the notification.
    Example: "added_to_entity"

  data:
    Type: App\Data\NotificationPayloadData
    allOf:
      Reference to: `NotificationPayloadData`
    Description: Data of notification. Will be flexible based on the notification type, but will at least contain a list of entity IDs the notification is related to.

  created_at:
    Type: integer
    Example: 1764988634

  read_at:
    Type: integer
    Example: 1764988634



### UserNotificationSettings

Type: object
Properties:
  new_phrase:
    Type: array
    Items: 
      Type: string
      Example: "broadcast"

    Description: List of channels for new phrase notifications. Every time a batch of phrases is created in any of the projects where the user holds a translator role, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

  invitation:
    Type: array
    Items: 
      Type: string
      Example: "broadcast"

    Description: List of channels for invitation notifications. Every time a user is invited to a project or organization, the user will receive a notification through the selected channels. Leave empty to not receive any notifications.

  added_to_entity:
    Type: array
    Items: 
      Type: string
      Example: "broadcast"

    Description: List of channels for added to entity notifications. Every time a user is directly added to a project or organization (without going through the invitation flow), the user will receive a notification through the selected channels. Leave empty to not receive any notifications.



### UserSettingsData

Type: object
Properties:
  notifications:
    Type: App\Data\UserNotificationSettings
    allOf:
      Reference to: `UserNotificationSettings`
    Description: The user notification settings. Available channels: broadcast, mail. Broadcast should be used to send in-app notifications to the user; mail should be used to send email notifications to the user.

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: The maximum amount that can be drawn from the monthly balance of the user.
    Example: 100

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Whether auto recharge is enabled for the user
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: The amount of balance that must be left in the balance of the user to trigger auto recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: The amount of balance that will be added to the balance of the user when auto recharge is triggered.
    Example: 20

  allow_draw_organizations:
    Type: boolean
    Default: true
    Description: The allow draw organizations for the user
    Example: true

  draw_organizations_limit_monthly:
    Type: number
    Format: float
    Description: The draw organizations limit monthly for the user
    Example: 100



### NotificationPayloadData

Type: object
Properties:
  entity_ids:
    Type: array
    Items: 
      Type: string
      Example: "4da3d8ec-1ad4-4edd-bd7c-e30e3521ed89"




### OrganizationSettingsData

Type: object
Properties:
  use_translation_memory:
    Type: boolean
    Default: true
    Description: Determines whether the system should look in Translation Memory when using the translation search algorithm.
    Example: true

  machine_translate_new_phrases:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should generate a machine translation for each new phrase created; this will only happen if the phrase doesnt have a translation/machine translation in the Organizations Translation Memory or if it has machine translations in the Organizations Translation Memory but the use_translation_memory setting is disabled.
    Example: true

  use_machine_translations:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should return machine translations when searching for translations through the translations endpoint.
    Example: true

  translate_base_locale_only:
    Type: boolean
    Default: false
    Description: Organization wide setting that when enabled will detect the language of your phrases before machine translating.  If it matches base_locale, it will be allowed to machine translate. If another locale is detected, the phrase will be marked to never translate automatically. Language detection may have an additional cost per phrase.  Use this option if you have mixed language content and want to be sure that other languages stay in their original form.
    Example: true

  machine_translator:
    Type: enum
    Enum: ["default", "google", "amazon", "chatgpt4o", "xai", "deepl"]
    Default: "default"
    Description: Organization wide setting that determines the default machine translator to use in the projects.
    Example: "deepl"

  broadcast_translations:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should broadcast translation updates to connected clients in real-time.
    Example: true

  monthly_credit_usage_limit:
    Type: number
    Format: float
    Description: Organization wide setting that determines the monthly usage limit for the organization.
    Example: 20

  auto_recharge_enabled:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should automatically recharge the organization when the usage limit is reached.
    Example: true

  auto_recharge_threshold:
    Type: number
    Format: float
    Description: Organization wide setting that determines the threshold for automatic recharge.
    Example: 20

  auto_recharge_amount:
    Type: number
    Format: float
    Description: Organization wide setting that determines the amount to recharge.
    Example: 20

  auto_recharge_source:
    Type: enum
    Enum: ["organization_owner_balance", "credit_card", "account_balance_or_credit_card", "credit_card_or_account_balance"]
    Default: "account_balance_or_credit_card"
    Description: Organization wide setting that determines the source of the automatic recharge.
    Example: "organization_owner_balance"

  allow_draw_projects:
    Type: boolean
    Default: false
    Description: Organization wide setting that determines whether the system should allow projects to draw funds from the organization.
    Example: true

  draw_projects_limit_monthly:
    Type: number
    Format: float
    Description: Organization wide setting that determines the monthly limit for drawing funds from the projects.
    Example: 20



### TranslationData

Type: object
Properties:
  phrase_id:
    Type: string
    Description: ID of the phrase to translate.
    Example: "123e4567-e89b-12d3-a456-426614174000"

  translation:
    Type: string
    Description: Translation in queried locale.
    Example: "Inicio"

  translatable:
    Type: boolean
    Default: true
    Description: If this flag is false, the phrase will be marked as untranslatable for requested locale and all project target locales of the same language the user has access to. Default: true
    Example: true



### DeleteTrashData

Type: object
Properties:
  items:
    Type: array
    Items: 
      allOf:
        Reference to: `TrashItemData`

    Description: List of items to permanently delete



### RestoreTrashData

Type: object
Properties:
  items:
    Type: array
    Items: 
      allOf:
        Reference to: `TrashItemData`

    Description: List of items to restore



