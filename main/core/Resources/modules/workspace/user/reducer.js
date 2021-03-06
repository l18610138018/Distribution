import {makeReducer} from '#/main/app/store/reducer'

import {FORM_SUBMIT_SUCCESS} from '#/main/app/content/form/store/actions'

import {reducer as pendingReducer} from '#/main/core/workspace/user/pending/reducer'
import {reducer as usersReducer} from '#/main/core/workspace/user/user/reducer'
import {reducer as groupsReducer} from '#/main/core/workspace/user/group/reducer'
import {reducer as rolesReducer} from '#/main/core/workspace/user/role/reducer'
import {reducer as workspaceReducer} from '#/main/core/workspace/user/parameters/reducer'

const reducer = {
  users: usersReducer,
  groups: groupsReducer,
  roles: rolesReducer,
  pending: pendingReducer,
  workspace: makeReducer({}, {
    [FORM_SUBMIT_SUCCESS+'/parameters']: (state, action) => action.updatedData
  }),
  parameters: workspaceReducer,
  restrictions: makeReducer({}, {})
}

export {
  reducer
}
