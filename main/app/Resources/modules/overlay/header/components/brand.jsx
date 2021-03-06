import React from 'react'
import {PropTypes as T} from 'prop-types'

import {asset} from '#/main/app/config'
import {url} from '#/main/app/api'

// todo add alt

const SvgLogo = props =>
  <svg className="app-header-logo">
    <use xlinkHref={`${asset(props.url)}#logo-sm`} />
  </svg>

const StandardLogo = props =>
  <img
    className="app-header-logo"
    src={asset(props.url)}
  />

const HeaderBrand = props => {
  return (
    props.redirectHome ?
      <a href={url(['claro_index'])} className="app-header-item app-header-brand hidden-xs">
        {props.logo.colorized &&
          <SvgLogo url={props.logo.url} />
        }
        {!props.logo.colorized &&
          <StandardLogo url={props.logo.url} />
        }
      </a>
      :
      <div className="app-header-item app-header-brand hidden-xs">
        {props.logo.colorized &&
          <SvgLogo url={props.logo.url} />
        }
        {!props.logo.colorized &&
          <StandardLogo url={props.logo.url} />
        }
      </div>
  )
}


HeaderBrand.propTypes = {
  redirectHome: T.bool.isRequired,
  logo: T.shape({
    url: T.string.isRequired,
    colorized: T.bool
  }).isRequired
}

export {
  HeaderBrand
}
